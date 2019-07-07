#include <stdlib.h>
#include <stdio.h>
#include <string.h>

#include <iostream>
#include <algorithm>
#include "minijson.h"
using namespace minijson;

typedef struct {
	const char *name; double val;
} jsonparams_t;

typedef struct {
	double rect[4], *coord;
	size_t count;
} heatdata_t;

void json2coord(const char *json_str, heatdata_t *data, jsonparams_t *params) {
	size_t i = 0, count = 0;
	double *buf = NULL;

  value v;
  error e = parse(json_str, v);
  if (e == no_error) {
    object& o = v.get<object>();
    array& a = o["coord"].get<array>();

		count = a.size();
		buf = (double*)malloc(count * sizeof(double));

		for (array::iterator it = a.begin(); it != a.end(); it++) {
			double d; string& o = it->get<string>();
			if (!it->is<double>()) {
				d = strtod(o.c_str(), NULL);
			} else {
				d = it->get<double>();
			}
			buf[i++] = d;
		}

		if (params) while (params->name != NULL) {
			if (o.find(params->name) != o.end()) {
				auto it = o[params->name];
				string& val = it.get<string>();
				if (!it.is<double>()) {
					params->val = strtod(val.c_str(), NULL);
				} else {
					params->val = it.get<double>();
				}
			}
			params++;
		}

		i = 0;
    a = o["rect"].get<array>();
		for (array::iterator it = a.begin(); i < 4 && it != a.end(); it++) {
			double d; string& o = it->get<string>();
			if (!it->is<double>()) {
				d = strtod(o.c_str(), NULL);
			} else {
				d = it->get<double>();
			}
			data->rect[i++] = d;
		}
  }

	data->coord = buf;
	data->count = count;
}

typedef struct {
	float *arr;
	size_t count;
} predictdata_t;

void json2predict(const char *json_str, predictdata_t *data, jsonparams_t *params) {
	size_t i = 0, count = 0;
	float *buf = NULL;

  value v;
  error e = parse(json_str, v);
  if (e == no_error) {
    object& o = v.get<object>();
    array& a = o["a"].get<array>();

		count = a.size();
		buf = (float*)malloc(count * sizeof(float));

		for (array::iterator it = a.begin(); it != a.end(); it++) {
			double d; string& o = it->get<string>();
			if (!it->is<double>()) {
				d = strtod(o.c_str(), NULL);
			} else {
				d = it->get<double>();
			}
			buf[i++] = d;
		}

		if (params) while (params->name != NULL) {
			if (o.find(params->name) != o.end()) {
				auto it = o[params->name];
				string& val = it.get<string>();
				if (!it.is<double>()) {
					params->val = strtod(val.c_str(), NULL);
				} else {
					params->val = it.get<double>();
				}
			}
			params++;
		}
  }

	data->arr = buf;
	data->count = count;
}

char *heatmap(const char *json_str, size_t *size);

#ifdef TESTMAIN
int main(int argc, char **argv) {
  const char* json_str = "{'coord':[2,'3',3,4,4,5,5,6], 'step':0.1, 'rect':[0,0,10,10], 'blur':4}";

#if 0
	jsonparams_t params[] = { { "step", 0 }, { NULL, 0 } }; 
	heatdata_t data;
	json2coord(json_str, &data, params);

	printf("step = %f\n", params[0].val);
	for (size_t i = 0; i < data.count; i++) {
		printf("[%i] %f\n", (int)i, data.coord[i]);
	}
#else
	size_t size = 0;
	char *mem = heatmap(json_str, &size);
	FILE *file;
	if (mem && (file = fopen("output.png", "wb"))) {
		fwrite(mem, 1, size, file);
		fclose(file);
	}
#endif

  return 0;
}
#endif
