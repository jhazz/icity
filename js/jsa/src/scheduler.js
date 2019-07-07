/*  globals jsa */

jsa.module({
name:'jsa.scheduler',
init:function(module){
  var defaults = {
      taskPoolCapacity: 4,
      taskPoolCount: 3,
      loopTime: 40 /* 40 msec = 1000/25fps */ ,
      maxLoopTime: 1000
    },
    C = {
      STATE_CREATED: 1,
      STATE_WORKING: 2,
      STATE_DELAYED: 4,
      STATE_BREAK: 8,
      STATE_TIMEOUT: 16,
      STATE_DONE: 32,
      STATE_FAIL: 64,
      STATE_DISPOSED: 128
    };

  jsa.scheduler = {};

  /** @class jsa.scheduler.Worker */
  jsa.scheduler.Worker = function (params) {
    var i;
    if (!params) {
      params = {};
    }
    this.params = params;
    for (i in defaults) {
      if (params[i] === undefined) {
        params[i] = defaults[i];
      }
    }
    this.pools = new Array(params.taskPoolCount); // pools[0]-realtime, pools[1] - normal, pools[2]-idle
    for (i = 0; i < params.taskPoolCount; i++) {
      this.pools[i] = new jsa.scheduler.TaskPool(this, params.taskPoolCapacity);
    }
    this.queue = []; // очередь задач (перетекает в пулы по мере их освобождения)
  };

  /** @class jsa.scheduler.TaskPool */
  /**
   * Конструктор пула задач для работника
   * @constructor jsa.scheduler.TaskPool
   * @param {taskman.Worker} Работник, который выполняет пулы задач
   * @param {int} capacity емкость пула (сколько задач можно добавить)
   **/
  jsa.scheduler.TaskPool = function (worker, capacity) {
    this.worker = worker;
    this.worker.now = Date.now();
    this.headPos = -1;
    this.emptyPos = -1;
    this.count = 0;
    this.capacity = capacity;
    this.leavePos = -1;
    this.items = new Array(capacity);
  };


  /**
   * Добавляет новую задачу в пул задач работника
   * @param {Object} target объект, относительно которого вызывается задача
   * @param {Function} execute функция, которая будет вызываться каждый цикл шедулера
   * @param {*} результаты от предыдущего вызова
   * @param {Function} afterDone  функция которая будет вызвана по окончании задачи
   * @param {Function} afterFail функция которая будет вызвана при ошибке выполнения
   * @param {Function} afterTimeout функция, которая будет вызвана при превышении задачей требуемого времени
   * @param {int} timeoutmsec время через которое задача должна прекратить работу
   * @param {int} delaymsec задержка в миллисекундах, через которое задача должна начать работу
   * @return {int} позиция новой задачи в пули [0..n] или -1 если пул заполнен
   **/
  jsa.scheduler.TaskPool.prototype.addTask = function (target, execute, results,
        onInit, afterDone, afterFail, afterTimeout, intervalmsec, timeoutmsec, delaymsec) {
    var task;
    if (this.count >= this.capacity) {
      console.log("TaskPool overflow. Maximum tasks is " + this.capacity + ", task " + results + " rejected");
      return -1;
    }
    if (this.emptyPos == -1) {
      task = {
        pool: this,
        nextPos: this.headPos,
        pos: this.count++
      };
      this.items[task.pos] = task;
    }
    else {
      task = this.items[this.emptyPos];
      this.emptyPos = task.nextPos;
      task.nextPos = this.headPos;
      this.count++;
    }
    this.headPos = task.pos;
    task.target = target;
    task.execute = execute;
    task.onInit = onInit;
    task.results = results;
    task.state = C.STATE_CREATED;
    task.afterDone = afterDone; // установит обработчик успеха в undefined в случае отсутствия аргумента
    task.afterFail = afterFail; // установит обработчик ошибки в undefined в случае отсутствия аргумента
    task.afterTimeout = afterTimeout; // если doTimeout будет undefined, то будет вызван doFail
    task.intervalmsec=intervalmsec;
    task.timeoutTill = (timeoutmsec !== undefined) ? this.worker.now + timeoutmsec : undefined;

    if (delaymsec !== undefined) {
      task.delayedTill = this.worker.now + delaymsec;
      task.state |= C.STATE_DELAYED;
    } else {
      task.delayedTill = undefined;
      task.state |= C.STATE_WORKING;
    }
    return task.pos;
  };

  /**
   * Выполнение работником задач из пулов в зависимости от выделенного времени
   **/
  jsa.scheduler.Worker.prototype.loopAll = function () {
    this.now = Date.now();
    var runStartAt = this.now;
    // execute all tasks of the 'realtime' pool. But stop it after reach 1000 msec
    this.loop(0, runStartAt + this.params.maxLoopTime);
    // execute at least one task of the 'normal' pool in the time slot of 25 fps (40 msec)
    this.loop(1, runStartAt + this.params.loopTime, true);
    if (this.pools[1].leavePos == -1) {
      // execute tasks of the 'idle' pool within rest time
      this.loop(2, runStartAt + this.params.loopTime, true);
    }
  };

  /**
   * Make a loop
   * @param int number of pool
   * @param boolean means that loop should continue from stopped position
   **/
  jsa.scheduler.Worker.prototype.loop = function (poolNo, stopAtTime, startFromLeavePos) {
    var pool = this.pools[poolNo];
    if (!pool.count) return;
    var pos, task, prevPos = -1,
      nextPos, exitCycleOn,
      resolved = function (results) {
        if (results !== undefined) {
          task.results = results;
        }
        task.results = results;
        task.state &= ~C.STATE_WORKING;
        task.state |= C.STATE_DONE;
        if (task.afterDone!==undefined) {
          task.afterDone(results,task);
        }
      },
      rejected = function (failResults) {
        console.log("Task " + task.id + " is fail. Result is " + failResults);
        if (failResults !== undefined) {
          task.results = failResults;
        }
        task.results = failResults;
        task.state &= ~C.STATE_WORKING;
        task.state |= C.STATE_FAIL;
      };

    exitCycleOn = pos = ((startFromLeavePos) && (pool.leavePos !== -1)) ? pool.yieldPos : -1;
    pool.leavePos = -1;

    while (true) {
      if (pos == -1) {
        pos = pool.headPos;
      }
      task = pool.items[pos];
      nextPos = task.nextPos;

      if (task.state & C.STATE_DISPOSED) {
        console.log("Disposed task found in active task list!");
        break;
      }

      if (task.timeoutTill<this.now) {
        task.state |= C.STATE_TIMEOUT;
        if(task.afterTimeout!==undefined) {
          task.afterTimeout(task);
        }
      } else {
        if (task.state & C.STATE_DELAYED) {
          if ((task.delayedTill !== undefined) && (task.delayedTill < this.now)) {
            task.state ^= C.STATE_DELAYED | C.STATE_WORKING; // сбрасываем бит "отсрочки", включаем бит "работай"
          }
        }

        if ((task.state & (C.STATE_CREATED | C.STATE_WORKING)) == (C.STATE_CREATED | C.STATE_WORKING)) {
          if (task.onInit !== undefined) {
            task.onInit(resolved, rejected);
          }
          task.state &= ~C.STATE_CREATED; // reset CREATED bit
        }

        if ((task.execute !== undefined) && (task.state & C.STATE_WORKING)) {
          try {
            task.execute(resolved, rejected);
            if(task.intervalmsec!==undefined){
              task.state ^= C.STATE_DELAYED | C.STATE_WORKING;
              task.delayedTill=this.now+task.intervalmsec;
            }
          }
          catch (exception) {
            task.state = C.STATE_FAIL;
            task.results = exception;
            console.log("ERROR: " + exception.stack);
          }
        }
      }

      if (task.state & (C.STATE_DONE | C.STATE_FAIL | C.STATE_TIMEOUT | C.STATE_BREAK)) {
        task.state |= C.STATE_DISPOSED;
        if (prevPos != -1) {
          pool.items[prevPos].nextPos = task.nextPos;
        }
        else {
          pool.headPos = task.nextPos;
        }
        task.nextPos = pool.emptyPos;
        pool.emptyPos = task.pos;
        pool.count--;
      }
      else {
        prevPos = pos;
      }

      pos = nextPos;
      if (pos == exitCycleOn) {
        break;
      }
      if (stopAtTime !== undefined) {
        if ((this.now = Date.now()) > stopAtTime) {
          pool.leavePos = pos;
          break;
        }
      }
    }
  };

  jsa.scheduler.Worker.prototype.breakTaskByPos = function (poolNo, taskPos) {
    var pool = this.pools[poolNo];
    if (taskPos < pool.capacity) {
      var task = pool.items[taskPos];
      if ((task !== undefined) && (!(task.state & C.STATE_DISPOSED))) {
        task.state |= C.STATE_BREAK;
      }
    }
  };

  /**
   * Просто выводит список задач из пула
   */
  jsa.scheduler.Worker.prototype.dumpPoolToHtml = function (poolNo) {
    var stateText, color, tds, s = "",
      i, pool = this.pools[poolNo],
      task;
    if (!pool) {
      return "Pool " + poolNo + " is unknown";
    }
    for (i = 0; i < pool.capacity; i++) {
      color = '#ffffff';
      tds = "";
      task = pool.items[i];
      if (!task) {
        tds = "EMPTY";
        color = '#505050';
      }
      else {
        stateText = "";
        if (task.state & C.STATE_CREATED) {
          stateText += "Creat";
        }
        if (task.state & C.STATE_WORKING) {
          stateText += "Work";
          color = "#40f040";
        }
        if (task.state & C.STATE_DELAYED) {
          stateText += "Delay";
          color = "#008080";
        }
        if (task.state & C.STATE_TIMEOUT) {
          stateText += "Time";
          color = "#f03000";
        }
        if (task.state & C.STATE_DONE) {
          stateText += "Done";
          color = "#8080f0";
        }
        if (task.state & C.STATE_FAIL) {
          stateText += "Fail";
          color = "#f0f060";
        }
        if (task.state & C.STATE_DISPOSED) {
          stateText += "Disp";
          color = "#a0a0a0";
        }
        tds = "<b>[#" + i + "]</b>&nbsp;" + task.results + " <a href='javascript:W.breakTaskByPos(0," + i + ");W.run();log(W.dumpPoolToHtml(0));'>(break)</a><br>" + stateText + "->[#" + task.nextPos + "]";
      }
      s += "<td style='font-size:11px;' bgcolor='" + color + "' width='200px'>" + tds + "</td>";
    }
    s = "<table border=0 cellspacing=1 cellpadding=1><tr><td width='200px' bgcolor='#c0c0c0'>" +
      "head:[#" + pool.headPos + "], empty:[#" + pool.emptyPos + "], (" + pool.count + "/" + pool.capacity + ")" +
      "</td>" + s + "<td><a href='javascript:W.pools[0].addTask(0,function(task){},Date.now());W.run();log(W.dumpPoolToHtml(0));'>[+]</a></td></tr></table>";
    return s;
  };

  jsa.scheduler.Worker.prototype.async = function (job, plan) {
    var poolNo = plan.priority || 1;
    job.plan = plan;
    job.worker = this;
    if (job.planPos === undefined) job.planPos = job.plan;
    this.pools[poolNo].addTask(
      job,
      plan.execute,
      "Task for job " + job.name,
      job.planPos.init,
      function () { // doneFunc
        // this - task
        var next = this.plan.done;
        if (typeof next == "function") {
          next(this);
          job.planPos = undefined;
        }
        else {
          this.job.planPos = next;
          if (this.job.planPos !== undefined) {
            this.job.worker.async(job, plan);
          }
          else {
            job.planPos = undefined;
          }
        }
      },
      plan.fail,
      plan.timeout,
      plan.timeoutmsec,
      plan.delaymsec
    );
  };

  jsa.scheduler.test=function (){alert ('Hello!');};

  jsa.define({clsName:'jsa.scheduler',
    actions:{
      'test':jsa.scheduler.test,
      'loopAll':jsa.scheduler.loopAll,
      'async':jsa.scheduler.async
    }
  });
  return true;
}});


/*
var W;

function start(){
    W=new jsa.scheduler.Worker();

    var planForJob1={
        init:function(){
            var self=this;
            self.timeoutArrived=0;
            window.setTimeout(function(){
                log("window.setTimeout done");
                self.timeoutArrived=1;
            },3000);
        },execute:function(resolved,rejected){
            if(this.timeoutArrived) {
                log("Send resolved: I have done");
                resolved("I have done");
            }
        },done:{
            init:function(value){
                log("Next plan position gets value:"+value);
                }
        }
        ,fail:function(){
            log("Fail detected");
        },timeout:function(){
            log("Timeout detected");
        }
    };

    W.async({name:'job1'},planForJob1);
    log(W.dumpPoolToHtml(1));
    W.run();
    log(W.dumpPoolToHtml(1));

}


function check1(){
    W=new jsa.scheduler.Worker();
    W.pools[0].addTask(0,function(task){},"task1");
    W.pools[0].addTask(0,function(done,fail){
        done("(Done of task "+this.value+")");

    },"task2");
    log("Added two tasks");
    log(W.dumpPoolToHtml(0));
    W.run();
    log("After executing a loop");
    log(W.dumpPoolToHtml(0));

    W.pools[0].addTask(0,function(){},"task3");
    log("Added task3");
    log(W.dumpPoolToHtml(0));

    log("One more loop");
    W.run();
    log(W.dumpPoolToHtml(0));

    W.pools[0].addTask(0,function(){},"task3");
    log("Added task3");
    log(W.dumpPoolToHtml(0));
    W.pools[0].addTask(0,function(){},"task4");
    W.pools[0].capacity+=2;
    W.pools[0].addTask(0,function(){},"task5");
    log(W.dumpPoolToHtml(0));
    W.run();
    log("After Loop");
    log(W.dumpPoolToHtml(0));

    log("Add task6 with autokill function");
    W.pools[0].addTask(0,function(task){task.state|=32},"task6");
    log(W.dumpPoolToHtml(0));
    W.run();
    log("After second loop task6 should be removed and count will be 3");
    log(W.dumpPoolToHtml(0));

    log("Killing task #2");
    W.breakTaskByPos(0,2);
    W.run();
    log(W.dumpPoolToHtml(0));

    log("Adding task7");
    W.pools[0].addTask(0,function(){},"task7");
    log(W.dumpPoolToHtml(0));

    log("Kill 0 and 2 tasks");
    W.breakTaskByPos(0,0);
    W.breakTaskByPos(0,2);
    W.run();
    log(W.dumpPoolToHtml(0));

    log("Kill task #1 and #3");
    W.breakTaskByPos(0,1);
    W.breakTaskByPos(0,3);
    W.run();
    log(W.dumpPoolToHtml(0));

    log("Adding task8");
    W.pools[0].addTask(0,function(){},"task8");
    log(W.dumpPoolToHtml(0));

    log("Kill task #0 and #3");
    W.breakTaskByPos(0,0);
    W.breakTaskByPos(0,3);
    W.run();
    log(W.dumpPoolToHtml(0));

    W.pools[0].addTask(0,function(){},"task9");
    log(W.dumpPoolToHtml(0));
    W.pools[0].addTask(0,function(){},"task10");
    log(W.dumpPoolToHtml(0));
    W.pools[0].addTask(0,function(){},"task11");
    log(W.dumpPoolToHtml(0));
    W.pools[0].addTask(0,function(){},"task12");
    log(W.dumpPoolToHtml(0));
    W.breakTaskByPos(0,0);
    W.breakTaskByPos(0,1);
    W.breakTaskByPos(0,2);
    W.breakTaskByPos(0,3);
    W.breakTaskByPos(0,4);
    W.breakTaskByPos(0,5);
    W.run();
    log(W.dumpPoolToHtml(0));
}

function log(s){
    document.getElementById("myLog").insertAdjacentHTML("beforeend",s);
}

*/