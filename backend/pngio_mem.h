
#define PNG_DEBUG 0
#include <png.h>

/* structure to store PNG image bytes */
struct png_mem_encode {
  char *buffer;
  size_t size;
};

static void bitmap_write_png_cb(png_structp png_ptr, png_bytep data, png_size_t length) {
	/* with libpng15 next line causes pointer deference error; use libpng12 */
	struct png_mem_encode* p = (struct png_mem_encode*)png_get_io_ptr(png_ptr); /* was png_ptr->io_ptr */
	size_t nsize = p->size + length;

	/* allocate or grow buffer */
	if (p->buffer) p->buffer = (char*)realloc(p->buffer, nsize);
	else p->buffer = (char*)malloc(nsize);

	if (!p->buffer) png_error(png_ptr, "Write Error");

  /* copy new bytes to end of buffer */
	memcpy(p->buffer + p->size, data, length);
	p->size += length;
}

uint8_t *bitmap_write_png_mem(bitmap_t *bm, size_t *size) {
	png_bytep *row_pointers; png_structp png_ptr = NULL; png_infop info_ptr = NULL;
	int y, width, height, bpp, stride;
	uint8_t *data; png_byte bit_depth = 8;
	png_byte color_type;
	const char *errstr = "param_check";
	struct png_mem_encode state;
	state.buffer = NULL;
	state.size = 0;

	if (!bm) goto err;
	width = bm->width; height = bm->height;
	bpp = bm->bpp; stride = bm->stride; data = bm->data;
	if (bpp < 1 || bpp > 4) goto err;
	color_type =
		bpp == 3 ? PNG_COLOR_TYPE_RGB :
		bpp == 4 ? PNG_COLOR_TYPE_RGB_ALPHA :
		bpp == 1 ? PNG_COLOR_TYPE_GRAY : PNG_COLOR_TYPE_GRAY_ALPHA;

	errstr = "create_write_struct";
	png_ptr = png_create_write_struct(PNG_LIBPNG_VER_STRING, NULL, NULL, NULL);
	if (!png_ptr) goto err;

	errstr = "create_info_struct";
	info_ptr = png_create_info_struct(png_ptr);
	if (!info_ptr) goto err;

	png_set_write_fn(png_ptr, &state, bitmap_write_png_cb, NULL);

	errstr = "write_info";
	png_set_IHDR(png_ptr, info_ptr, width, height,
				 bit_depth, color_type, PNG_INTERLACE_NONE,
				 PNG_COMPRESSION_TYPE_BASE, PNG_FILTER_TYPE_BASE);
	png_write_info(png_ptr, info_ptr);

	row_pointers = (png_bytep*)malloc(sizeof(png_bytep) * height);
	for (y = 0; y < height; y++)
		row_pointers[y] = (png_byte*)(data + y*stride);

	errstr = "write_image";
	png_write_image(png_ptr, row_pointers);
	free(row_pointers);

	errstr = "write_end";
	png_write_end(png_ptr, NULL);
	errstr = NULL;
err:
	if (errstr) printf("[write_png] %s failed\n", errstr);
	if (png_ptr) png_destroy_write_struct(&png_ptr, &info_ptr);

	*size = state.size;
	return (uint8_t*)state.buffer;
}

