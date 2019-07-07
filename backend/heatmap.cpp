#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <stdint.h>

#include "boxfilter.h"
#include "bitmap.h"
#include "pngio_mem.h"

typedef struct {
	const char *name; double val;
} jsonparams_t;

typedef struct {
	double rect[4], *coord;
	size_t count;
} heatdata_t;

void json2coord(const char *json_str, heatdata_t *data, jsonparams_t *params);

char *heatmap(const char *json_str, size_t *size) {
	jsonparams_t params[] = { { "step", 0.1 }, { "blur", 3 }, { "mul", 10 }, { NULL, 0 } }; 
	heatdata_t data;
	json2coord(json_str, &data, params);
	if (!data.coord) return 0;
	char *mem = NULL;

	int blur = params[1].val;
	if (blur > 200) blur = 200;
	int w = (data.rect[2] - data.rect[0]) / params[0].val;
	int h = (data.rect[3] - data.rect[1]) / params[0].val;

	if (w <= 0 || h <= 0) {
		free(data.coord);
		return mem;
	}

	int fst = w + 1;
	float *fdata = (float*)malloc(fst * (h + 1) * sizeof(float));
	memset(fdata, 0, fst * (h + 1) * sizeof(float));
	for (size_t i = 0; i < data.count - 1; i += 2) {
		double x = data.coord[i] - data.rect[0];
		double y = data.coord[i+1] - data.rect[1];
		if (x < 0 || y < 0) continue;
		x /= params[0].val;
		y /= params[0].val;
		int x0 = x, y0 = y;
		if (x0 > w || y0 >= h) continue;
		x -= x0; y -= y0;
		fdata[fst * y0 + x0] += (1-x) * (1-y);
		fdata[fst * y0 + x0 + 1] += x * (1-y);
		fdata[fst * y0 + fst + x0] += (1-x) * y;
		fdata[fst * y0 + fst + x0 + 1] += x * y;
	}
	if (blur >= 2) {
		RunFilter(fdata, fdata, w, h, 1, fst, blur);
	}

	uint8_t pal[256][4];
	for (int i = 0; i < 256; i++) {
		pal[i][0] = 255;
		pal[i][1] = 0;
		pal[i][2] = 255-i;
		pal[i][3] = i;
	}

	bitmap_t *bmp = bitmap_create(w, h, 4);
	{
		uint8_t *data = bmp->data;
		int j, x, y, st = bmp->stride;
		for (y = 0; y < h; y++)
		for (x = 0; x < w; x++) {
			float a = fdata[fst * y + x];
			int a1 = a * 255.0f * params[2].val + 0.5f;
			if (a1 < 0) a1 = 0;
			if (a1 > 255) a1 = 255;
			for (j = 0; j < 4; j++)
				data[st * y + x * 4 + j] = pal[a1][j];
		}
	}
	mem = (char*)bitmap_write_png_mem(bmp, size);
	bitmap_free(bmp);
	free(fdata);
	free(data.coord);
	return mem;
}

