/** jslint globals:console */

var taskman=(function(_global){
    var defaults={taskPoolCapacity:4,taskPoolCount:3},
        taskman=_global.taskman={},
        C={
        STATE_CREATED:1,
        STATE_WORKING:2,
        STATE_DELAYED:4,
        STATE_BREAK:8,
        STATE_TIMEOUT:16,
        STATE_DONE:32,
        STATE_FAIL:64,
        STATE_DISPOSED:128
    };
    
    taskman.Worker=function(params){
        var i;
        if(!params){ params=defaults;}
        this.pools=new Array(params.taskPoolCount); // pools[0] - highest priority, pools[1] - normal, pools[2]-lowest 
        for (i=0;i<params.taskPoolCount;i++) {
            this.pools[i]=new taskman.TaskPool(this,params.taskPoolCapacity);
        }
        this.queue=[]; // очередь задач (перетекает в пулы по мере их освобождения)
    };
    
    /**
     * sfsdfsdf
     * @description zzzzzzzzzzzzzzzzzzzz
     * @constructor taskman.TaskPool
     * @param {int} capacity емкость пула (сколько задач можно добавить)
     **/
    taskman.TaskPool=function(worker,capacity){
        this.worker=worker;
        this.headPos=-1;
        this.emptyPos=-1;
        this.count=0;
        this.capacity=capacity;
        this.leavePos=-1;
        this.items=new Array(capacity);
    };
    
    
    
    /**
     * Функция addTask добавляет новую задачу в пул задач работника
     * @param {Object} selft объект, относительно которого вызывается задача
     * @param {Function} execute функция, которая будет вызываться
     * @param {*} value от предыдущего вызова
     * @param {Function} afterDone  функция которая будет вызвана по окончании задачи
     * @param {Function} afterFail функция которая будет вызвана при ошибке выполнения
     * @param {Function} afterTimeout функция, которая будет вызвана при превышении задачей требуемого времени
     * @param {int} timeoutmsec время через которое задача должна прекратить работу
     * @param {int} delaymsec задержка в миллисекундах, через которое задача должна начать работу
     * @return {int} позиция новой задачи в пули [0..n] или -1 если пул заполнен
     **/
    taskman.TaskPool.prototype.addTask=function(self, execute, value, afterDone, afterFail, afterTimeout, timeoutmsec, delaymsec){
        var task;
        if(this.count>=this.capacity) {
            console.log("TaskPool overflow. Maximum tasks is "+this.capacity+", task "+value+" rejected");
            return -1;
        }
        if(this.emptyPos==-1){
            task={pool:this, nextPos: this.headPos, pos:this.count++};
            this.items[task.pos]=task;
        } else {
            task=this.items[this.emptyPos];
            this.emptyPos=task.nextPos;
            task.nextPos=this.headPos;
            this.count++;
        }
        this.headPos=task.pos;
        task.self=self;
        task.execute=execute;
        task.value=value;
        task.state=C.STATE_CREATED;
        task.afterDone=afterDone; // установит обработчик успеха в undefined в случае отсутствия аргумента
        task.afterFail=afterFail; // установит обработчик ошибки в undefined в случае отсутствия аргумента
        task.afterTimeout=afterTimeout; // если doTimeout будет undefined, то будет вызван doFail
        
        if(timeoutmsec!==undefined) {
            timeoutmsec=timeoutmsec || 10000;// 10 секунд по-умолчанию - достаточно для выдачи ошибок о том что не читается сайт или файл
            task.timeoutTill=this.worker.now+timeoutmsec;
        } else {
            task.timeoutTill=undefined;
        }
        
        if(delaymsec!==undefined) {
            task.delayedTill=this.worker.now+delaymsec;
            task.state|=C.STATE_DELAYED;
        } else {
            task.delayedTill=undefined;
            task.state|=C.STATE_WORKING;
        }
        return task.pos;
    };
 
    taskman.Worker.prototype.run=function(){
        this.now=Date.now();
        var runStartAt=this.now,stopAtTime=runStartAt+40; // give 40 msec to full circle (25 fps)
        this.loop(0); // execute all tasks from the 'realtime' pool
        this.loop(1,stopAtTime,true); // execute at least one task from the 'normal' pool
        if(this.pools[1].leavePos==-1){
            this.loop(2,stopAtTime,true); // execute tasks in 'idle' pool if has any time
        }
        
    };
    
    /**
     * Make a loop
     * @param int number of pool
     * @param boolean means that loop should continue from stopped position
     **/
    taskman.Worker.prototype.loop=function(poolNo,stopAtTime,startFromLeavePos){
        var pool=this.pools[poolNo];
        if(!pool.count) return;
        var pos, task, prevPos=-1, nextPos, exitCycleOn,
            resolved=function(value){
                if(value!==undefined){
                    task.value=value;
                }
                task.value=value;
                task.state&=~C.STATE_WORKING;
                task.state|=C.STATE_DONE;
            },
            rejected=function(value){
                console.log("Task"+task.value+" is fail. new value is "+value);
                if(value!==undefined){
                    task.value=value;
                }
                task.value=value;
                task.state&=~C.STATE_WORKING;
                task.state|=C.STATE_FAIL;
            };
            
        exitCycleOn=pos=((startFromLeavePos)&&(pool.leavePos!==-1))?pool.yieldPos:-1;
        pool.leavePos=-1;
        
        while(true){
            if(pos==-1){
                pos=pool.headPos;
            }
            task=pool.items[pos];
            nextPos=task.nextPos;
            
            if(task.state&C.STATE_DISPOSED){
                console.log("Disposed task found in active task list!");
                break;
            }
            if(task.state&C.STATE_DELAYED){
                if ((task.delayedTill!==undefined) && (task.delayedTill<this.now)){
                    task.state^=C.STATE_DELAYED|C.STATE_WORKING;// сбрасываем бит "отсрочки", включаем бит "работай"
                }
            }
            
            if((task.execute!==undefined)&&(task.state&C.STATE_WORKING)) {
                try {
                    task.execute(resolved,rejected);
                    //task.execute.apply(task,resolved,rejected);
                } catch (exception){
                    task.state=C.STATE_FAIL;
                    task.value=exception;
                    console.log("ERROR: "+exception.stack);
                }
                task.state&=~C.STATE_CREATED; // reset CREATED bit
            }
            
            if(task.state&(C.STATE_DONE|C.STATE_FAIL|C.STATE_TIMEOUT|C.STATE_BREAK)){
                task.state|=C.STATE_DISPOSED;
                if(prevPos!=-1){
                    pool.items[prevPos].nextPos=task.nextPos;
                } else {
                    pool.headPos=task.nextPos;
                }
                task.nextPos=pool.emptyPos;
                pool.emptyPos=task.pos;
                pool.count--;
            } else {
                prevPos=pos;
            }

            pos=nextPos;
			if(pos==exitCycleOn) {
				break;
			}
            if(stopAtTime!==undefined){
                if((this.now=Date.now())>stopAtTime){
                    pool.leavePos=pos;
                    break;
                }
            }
        }
    };
    
    taskman.Worker.prototype.breakTaskByPos=function(poolNo,taskPos){
        var pool=this.pools[poolNo];
        if(taskPos<pool.capacity){
            var task=pool.items[taskPos];
            if((task!==undefined) && (!(task.state&C.STATE_DISPOSED))) {
                task.state|=C.STATE_BREAK;
            }
        }
    };


    /**
     * 
     */
    taskman.Worker.prototype.dumpPoolToHtml=function(poolNo){
        var stateText,color,tds,s="",i,pool=this.pools[poolNo],task;
        if(!pool) {
            return "Pool "+poolNo+" is unknown";
        }
        for(i=0;i<pool.capacity;i++){
			color='#ffffff';
			tds="";
            task=pool.items[i];
            if(!task){
                tds="EMPTY"; color='#505050';
            } else {
				stateText="";
				if(task.state&C.STATE_CREATED){stateText+="Creat";}
				if(task.state&C.STATE_WORKING){stateText+="Work";color="#40f040";}
				if(task.state&C.STATE_DELAYED){stateText+="Delay";color="#008080";}
				if(task.state&C.STATE_TIMEOUT){stateText+="Time";color="#f03000";}
				if(task.state&C.STATE_DONE){stateText+="Done";color="#8080f0";}
				if(task.state&C.STATE_FAIL){stateText+="Fail";color="#f0f060";}
				if(task.state&C.STATE_DISPOSED){stateText+="Disp";color="#a0a0a0";}
                tds="<b>[#"+i+"]</b>&nbsp;"+task.value+" <a href='javascript:W.breakTaskByPos(0,"+i+");W.run();log(W.dumpPoolToHtml(0));'>(break)</a><br>"+stateText+"->[#"+task.nextPos+"]";
            }
			s+="<td style='font-size:11px;' bgcolor='"+color+"' width='200px'>"+tds+"</td>";
        }
        s="<table border=0 cellspacing=1 cellpadding=1><tr><td width='200px' bgcolor='#c0c0c0'>"+
        "head:[#"+pool.headPos+"], empty:[#"+pool.emptyPos+"], ("+pool.count+"/"+pool.capacity+")"
        +"</td>"+s+"<td><a href='javascript:W.pools[0].addTask(0,function(task){},Date.now());W.run();log(W.dumpPoolToHtml(0));'>[+]</a></td></tr></table>";
        return s;
    };
    return taskman;
})(window);

function log(s){
    document.getElementById("myLog").insertAdjacentHTML("beforeend",s);
}


/**
 * Lalala
 * @param {integer} z dlikkn
 * */
 
var W;
function start(){
    W=new taskman.Worker();
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
  //  debugger;
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
    
}
