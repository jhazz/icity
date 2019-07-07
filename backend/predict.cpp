#include <stdlib.h>
#include <stdio.h>
#include <string.h>

typedef struct {
	const char *name; double val;
} jsonparams_t;

typedef struct {
	float *arr;
	size_t count;
} predictdata_t;

void json2predict(const char *json_str, predictdata_t *data, jsonparams_t *params);

void RunFilter(float *in, float *out,
		int w, int h, int bpp, int stride, int blur);

char *predict(const char *json_str, size_t *size) {
	jsonparams_t params[] = { { "y", -1 }, { "d", -1 }, { "b", 3 }, { "b2", 15 }, { NULL, 0 } }; 
	predictdata_t data;
	json2predict(json_str, &data, params);
	if (!data.arr) return 0;
	char *mem = NULL;
	int n = data.count;
	int plong = 366, pshort = 30;

	if (n < plong+pshort) {
		free(data.arr);
		return mem;
	}
	float *temp = (float*)malloc(n * sizeof(float));

	// printf("# n = %d\n", n);

	RunFilter(data.arr, temp, n, 1, 1, 0, params[2].val);

	mem = (char*)malloc(12 * pshort + 10);

	float mdif = 0;
	for (int i = 0; i < pshort; i++) {
		mdif += data.arr[n-pshort+i] - data.arr[n-plong-pshort+i];
	}
	mdif /= pshort;
	// printf("mdif = %f\n", mdif);

	char *ptr = mem;
	ptr += sprintf(ptr, "{\"r\":[");

	for (int i = 0; i < pshort; i++) {
		ptr += sprintf(ptr, "%.6g, ", temp[n-plong+i] + mdif);
	}
	ptr -= 2;
	ptr += sprintf(ptr, "]}");
	*size = ptr - mem;

	free(temp);
	free(data.arr);
	return mem;
}

#ifdef TESTMAIN

static char* loadtext(const char *fn, size_t *num) {
	size_t n, j = 0; char *buf = 0;
	FILE *fi = fopen(fn, "rb");
	if (fi) {
		fseek(fi, 0, SEEK_END);
		n = ftell(fi);
		fseek(fi, 0, SEEK_SET);
		buf = (char*)malloc(n + 1);
		if (buf) {
			j = fread(buf, 1, n, fi);
			buf[j] = 0;
		}
		fclose(fi);
	}
	if (num) *num = j;
	return buf;
}

int main(int argc, char **argv) {
	size_t size = 0;
	char *json_str = loadtext("predict.json", &size);
	char *result = predict(json_str, &size);
	printf("result: %s\n", result);
  return 0;
}
#endif
