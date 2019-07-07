typedef struct {
	int width, height, bpp, stride; uint8_t *data;
} bitmap_t;

static bitmap_t *bitmap_create(int width, int height, int bpp) {
	bitmap_t *image; int stride = width * bpp;
	if ((unsigned)((width - 1) | (height - 1)) >= 0x8000) return 0;
	image = (bitmap_t*)malloc(sizeof(bitmap_t) + stride * height);
	if (!image) return 0;
	image->width = width;
	image->height = height;
	image->bpp = bpp;
	image->stride = stride;
	image->data = (uint8_t*)(image + 1);
	return image;
}

static void bitmap_free(bitmap_t *in) {
	if (in) free(in);
}

