#define CALC_BORDER

#define T float

static void boxfilter3(T *src, T *dst, int n, int st, int f0, int f1, double m3) {
	int i, j = (f0>>1)+(f1&-2), k = 0;
	double buf[65*2], x1, x2, x3, x4, a0, a1, a2 = 0, a3 = a2;

#define M3(a) a * m3

#ifdef CALC_BORDER
	for (i = 0; i < f1*2; i++) buf[i] = a2;
	a1 = src[j*st];
	for (i = 1-j; i < f0-j; i++) a1 += src[-i*st];
	for (; i < f0+f1*2-j; i++) {
		x1 = src[(i-f0 < 0 ? -(i-f0) : i-f0)*st];
		x2 = src[(i < 0 ? -i : i)*st];
		x4 = a2; a3 += x4 - buf[k];
		x3 = a1; a2 += x3 - buf[k+f1];
		buf[k] = x4;
		buf[k+f1] = x3;
		a1 += x2 - x1;
		if (++k >= f1) k = 0;
	}
	j = 0;
#else
	for (i = 0; i < f1*2; i++) buf[i] = a2;
	a1 = src[0];
	for (i = 1; i < f0; i++) a1 += src[i*st];
	for (; i < f0+f1*2; i++) {
		x1 = src[(i-f0)*st]; x2 = src[i*st];
		x4 = a2; a3 += x4 - buf[k];
		x3 = a1; a2 += x3 - buf[k+f1];
		buf[k] = x4;
		buf[k+f1] = x3;
		a1 += x2 - x1;
		if (++k >= f1) k = 0;
	}
#endif
	for (; i < n; i++) {
		x1 = src[(i-f0)*st]; x2 = src[i*st];
		a0 = M3(a3);
		x4 = a2; a3 += x4 - buf[k];
		x3 = a1; a2 += x3 - buf[k+f1];
		dst[st*j++] = a0;
		buf[k] = x4;
		buf[k+f1] = x3;
		a1 += x2 - x1;
		if (++k >= f1) k = 0;
	}
#ifdef CALC_BORDER
	for (; j < n-3; i++) {
		x1 = src[(i-f0 < n ? i-f0 : n*2-2-(i-f0))*st];
		x2 = src[(n*2-2-i)*st];
		a0 = M3(a3);
		x4 = a2; a3 += x4 - buf[k];
		x3 = a1; a2 += x3 - buf[k+f1];
		dst[st*j++] = a0;
		buf[k] = x4;
		buf[k+f1] = x3;
		a1 += x2 - x1;
		if (++k >= f1) k = 0;
	}
#endif
	a0 = M3(a3);
	x4 = a2; a3 += x4 - buf[k];
	x3 = a1; a2 += x3 - buf[k+f1];
	if (++k >= f1) k = 0;
	x1 = M3(a3);
	x4 = a2; a3 += x4 - buf[k];
	x2 = M3(a3);
	dst[st*j++] = a0;
	dst[st*j++] = x1;
	dst[st*j++] = x2;
#undef M3
}

void RunFilter(T *in, T *out,
		int w, int h, int bpp, int stride, int blur) {
	int i, j;

	int f0 = blur - ((blur-1) & 1);
	int f1 = blur + ((blur-1) & 1);
	double mul = 1.0 / (f0 * f1 * f1);
	// printf("f0 = %i, f1 = %i, mul = %f\n", f0, f1, mul);

	for (i = 0; i < h; i++) for (j = 0; j < bpp; j++)
		boxfilter3(in + i*stride + j, out + i*stride + j, w, bpp, f0, f1, mul);
	for (i = 0; i < w*bpp; i++) boxfilter3(out + i, out + i, h, stride, f0, f1, mul);
}

#undef T
