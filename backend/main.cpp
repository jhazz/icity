
#include <cstdio>
#include <httplib.h>
#include <iostream>
#include "pthread.h"

using namespace httplib;
using namespace std;

#if 0
#include <syslog.h>
#else
#define syslog(level, ...) { printf(__VA_ARGS__); printf("\n"); }
#endif

#ifdef CPPHTTPLIB_OPENSSL_SUPPORT
#define SERVER_CERT_FILE "./cert.pem"
#define SERVER_PRIVATE_KEY_FILE "./key.pem"
static SSLServer svr(SERVER_CERT_FILE, SERVER_PRIVATE_KEY_FILE);
#else
static Server svr;
#endif

static const char* pidfile = NULL;
static int term_flag = 0;

static void signal_handler(int sig) {
	switch (sig) {
		case SIGHUP:
			syslog(LOG_WARNING, "Received SIGHUP signal.");
			break;
		case SIGINT:
			if (!term_flag) {
				syslog(LOG_WARNING, "Received SIGTINT signal.");
				term_flag = 1;
			}
			break;
		case SIGTERM:
			if (!term_flag) {
				syslog(LOG_WARNING, "Received SIGTERM signal.");
				term_flag = 1;
			}
			break;
		default:
			syslog(LOG_WARNING, "Unhandled signal (%d) %s", sig, strsignal(sig));
			break;
	}

	if (term_flag) {
		static volatile char mutex = 0;
		printf("mutex = %i\n", mutex);
		if (!__sync_lock_test_and_set(&mutex, 1)) {
			svr.stop();
		}
	}
}

char *heatmap(const char *json_str, size_t *size);
char *predict(const char *json_str, size_t *size);

int main(int argc, const char **argv) {
	int port = 1234, daemonize = 0, restart = 0;
	const char* hostname = "localhost";
	const char* workpath = NULL;

	// Setup signal handling before we start
	signal(SIGHUP, signal_handler);
	signal(SIGTERM, signal_handler);
	signal(SIGINT, signal_handler);
	signal(SIGQUIT, signal_handler);

	while (argc > 1) {
		if (!strcmp(argv[1], "--daemon")) {
			daemonize = 1;
			argv++; argc--;
		}	else if (argc > 2 && !strcmp(argv[1], "--port")) {
			port = atoi(argv[2]);
			argv += 2; argc -= 2;
		}	else if (argc > 2 && !strcmp(argv[1], "--host")) {
			hostname = argv[2];
			argv += 2; argc -= 2;
		}	else if (argc > 2 && !strcmp(argv[1], "--pid")) {
			pidfile = argv[2];
			argv += 2; argc -= 2;
		}	else if (argc > 2 && !strcmp(argv[1], "--path")) {
			workpath = argv[2];
			argv += 2; argc -= 2;
		}	else if (!strcmp(argv[1], "--restart")) {
			restart = 1;
			argv++; argc--;
		}	else if (!strcmp(argv[1], "--kill")) {
			restart = -1;
			argv++; argc--;
		} else {
			printf("invalid option\n"); return 1;
		}
	}

	/* Change the current working directory */
	if (workpath)
		if ((chdir(workpath)) < 0) exit(EXIT_FAILURE);

	if (pidfile) {
		int fd = open(pidfile, O_RDONLY);
		if (fd >= 0) {
			if (restart) {
				char buf[32]; pid_t pid; int n;
				pid = getpid();

				n = read(fd, buf, sizeof(buf)-1);
				if (n <= 0) {
					syslog(LOG_INFO, "kill failed");
					return 1;
				}
				buf[n] = 0;
				pid = atoi(buf);

				syslog(LOG_INFO, "read daemon pid (%d)\n", pid);

				if (kill(pid, SIGTERM) == -1) {
					syslog(LOG_INFO, "kill failed");
					return 1;
				}
			} else {
				syslog(LOG_INFO, "daemon already running");
				close(fd);
				return 1;
			}
		}
	}

	if (restart < 0) return 0;

	if (daemonize) {
		/* Our process ID and Session ID */
		pid_t pid, sid;

		syslog(LOG_INFO, "starting the daemonizing process");
 		if ((pid = fork()) < 0) exit(EXIT_FAILURE);
		if (pid > 0) exit(EXIT_SUCCESS);
 		umask(0);
		if ((sid = setsid()) < 0) exit(EXIT_FAILURE);
	 
		/* Close out the standard file descriptors */
		close(STDIN_FILENO); close(STDOUT_FILENO); close(STDERR_FILENO);
	}

	if (pidfile) {
		FILE *file;
		if ((file = fopen(pidfile, "wb"))) {
			fprintf(file, "%d", getpid());
			fclose(file);	
		}
	}

	syslog(LOG_INFO, "daemon starting up (pid=%d)", getpid());

  svr.Get("/hi", [](const Request & /*req*/, Response &res) {
    res.set_content("Hello World!", "text/plain");
  });

	svr.Get("/sum",
		[](const Request &req, Response &res) {
			int a = atoi(req.get_param_value("a").c_str());
			int b = atoi(req.get_param_value("b").c_str());
			char buf[128];
			snprintf(buf, sizeof(buf), "{\"sum\":\"%d\"}", a + b);
			res.set_content(buf, "application/json");
	});

	svr.Post("/heatmap",
		[](const Request &req, Response &res) {
			auto json = req.get_param_value("json");
			printf("Request: %s\n", json.c_str());
			size_t size = 0;
			char *mem = heatmap(json.c_str(), &size);
			if (mem) {
				res.set_content((char*)mem, size, "image/png");
				free(mem);
			}
	});

	svr.Post("/predict",
		[](const Request &req, Response &res) {
			auto json = req.get_param_value("json");
			printf("Request: %s\n", json.c_str());
			size_t size = 0;
			char *mem = predict(json.c_str(), &size);
			if (mem) {
				res.set_content(mem, size, "application/json");
				free(mem);
			}
	});

	printf("The server started at %s:%d ...\n", hostname, port);

	int err = svr.listen(hostname, port);
	if (!err) {
		printf("listen error\n");
	}

	syslog(LOG_INFO, "exiting daemon process");

	if (pidfile) remove(pidfile);
	return 0;
}
