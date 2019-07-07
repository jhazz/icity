
#CXX = clang++
CXXFLAGS = -s -std=c++11 -I. -Wall -lpthread -lpng -O1 
#OPENSSL_SUPPORT = -DCPPHTTPLIB_OPENSSL_SUPPORT -I/usr/local/opt/openssl/include -L/usr/local/opt/openssl/lib -lssl -lcrypto
ZLIB_SUPPORT = -DCPPHTTPLIB_ZLIB_SUPPORT -lz

all: main

json2coord.o: json2coord.cpp minijson.h
	$(CXX) -c -o $@ $< $(CXXFLAGS)

json2coord.cpp: minijson.h
jsontest: json2coord.cpp heatmap.o json2coord.o
	$(CXX) -DTESTMAIN -o $@ $^ $(CXXFLAGS)
	./$@
	rm -f $@

heatmap.o: heatmap.cpp boxfilter.h bitmap.h pngio_mem.h
	$(CXX) -c -o $@ $< $(CXXFLAGS)

predict.o: predict.cpp
	$(CXX) -c -o $@ $< $(CXXFLAGS)

predicttest: predict.cpp heatmap.o json2coord.o
	$(CXX) -DTESTMAIN -o $@ $^ $(CXXFLAGS)
	./$@
	rm -f $@

main.o: main.cpp httplib.h
	$(CXX) -c -o $@ $< $(CXXFLAGS) $(OPENSSL_SUPPORT) $(ZLIB_SUPPORT)

main: main.o heatmap.o json2coord.o predict.o
	$(CXX) -o $@ $^ $(CXXFLAGS) $(OPENSSL_SUPPORT) $(ZLIB_SUPPORT)

clean:
	rm -f main *.o
