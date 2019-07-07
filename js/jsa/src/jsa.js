/**
 * @fileoverview The main module file
 * Loads in the topmost window and carry all libraries using by the child frames
 * @author Vlad Zbitnev
 *
 *
 * TODO NEXT
 * 1. Публикация событий
 * 2. Добавить подписку на удаление панели (destroy) к сплитерам, чтобы чувствовали удаление
 * 3. Добавить сплитер к обычной, не 'A'-панели, То есть до docSet'a
 * 4. Сделать аналогичное для набора панелей (A-docSet'a)
 *
 *
 */

/* jslint eqeqeq:false, indent: 4, maxerr: 230, white: true, browser: true, evil: true, nomen: true, plusplus: true, sloppy: true */
/* jshint eqeqeq:false, curly:true */

/* exported jsa */

/**
 * @type Boolean Overridden to true by the compiler when --closure_pass
 *     or --mark_as_compiled is specified.
 */
var COMPILED = true;
/**
 * @define {boolean} May be exluded by compiler
 */
var CREATE_CONSOLE = true;

/**
 * @type Boolean Compilation directive to leave debug messages in code
 */
var DEBUG = 1;


/** @type {Object} */
var jsf;
/**  @namespace */
var jsa = {
  /** @type {Object} */
  modules: {},
  /** @type {Object} */
  moduleLoaders: {},

  /** @type {number|null} */
  time: 0,
  /** @type {Object|integer} */
  isAppWindow: 1,
  /** @type {Window} */
  win: window,
  /** @type {Document} */
  doc: document,
  /** @type {Object} dependencies */
  deps: {},
  /** @type Array.<Object> registered IFrames by registerIFrame */
  frames: {},
  /** @type {Object}*/
  actions: {},
  /** @type {string} Path to the library*/
  LIB_URL: "src/",
  /** @type {number} *Default time interval for newest timelines in msec (100ms=10fps) */
  STAGE_TIMER_INTERVAL: 2000,
  /** @type {number} adds random number to url query */
  UNCACHEABLE_URLS: 1,
  /** @type {number} uid */
  lastUID: 0,
  nullFunction: function() {},
  subscribers: {},
  classes:{},
  mainScheduler:0,
  /** Module registration function calling from inside just loaded module */
  module:function (declarations) {
    // TODO: resolve dependencies, deferred loading
    var moduleName=declarations.name;
    if(jsa.modules[moduleName]) {
      return jsa.modules[moduleName].exports;
    }
    var exports={},moduleObject={exports:exports,ready:false};
    if (!!declarations.dependencies) {
      // проверяем зависимости потом
      alert('Не умею разбирать зависимости. detected dependencies');
    }
    var ok=declarations.init(moduleObject);
    if(ok!==false) {
      moduleObject.ready=true;
      jsa.emit('module:'+moduleName,'ready',moduleObject);
    }
  },


  registerClass:function (name,classDef) {
    this.classes[name]=classDef;
  },


  /**
   * Executes on class ready after load or inline
   * @param {Object} classDef Class object in Ext manner
   * @param {String} classDef.clsName class name
   * @param {Array} classDef.deps array of module urls/names the class depends on
   * @param {Function} classDef.inherits
   * @param {Function} classDef.constructor
   * @return {Object}
   * ВНИМАНИЕ! define определяет как модули так и классы
   */
  define: function(classDef) {
    if (DEBUG && (!classDef.clsName)) {
      throw new Error("Class should has a .clsName value defining a class namespace");
    }

    if (!classDef.constructor) {
      classDef.constructor = function() {
      };
    }
    jsa.classes[classDef.clsName]=classDef;

    if (classDef.methods) {
      for(var m in classDef.methods) {
        classDef.constructor.prototype[m] = classDef.methods[m];
      }
    }
    if (classDef.inherits) {
      jsa.inherits(classDef.constructor, classDef.inherits.constructor);
    }
    if(classDef.actions) {
      jsa.actions[classDef.clsName]={};
      for(var a in classDef.actions) {
        jsa.actions[classDef.clsName][a]=classDef.actions[a];
      }
    }
    return classDef.constructor;
  },
  /**
   * @param {!Object} childConstructor that parentConstructor prototype applying to
   * @param {!Object} parentConstructor gives prototype methods to child
   * to call inherited do this:
   *  {myClass}.superClass.{inheritedMethod}.call(this);
   */
  inherits: function(childConstructor, parentConstructor) {
    function TempConstructor() {
    }
    TempConstructor.prototype = parentConstructor.prototype;
    childConstructor.superClass = parentConstructor.prototype;
    childConstructor.prototype = new TempConstructor();
    childConstructor.prototype.constructor = childConstructor;
  },
  /**
   * Copy hash from source to destination
   * @param {any} destination hash that values to be copied to
   * @param {any} source hash
   */
  copy: function(destination, source) {
    var i;
    if (typeof source == 'object') {
      for (i in source) {
        if (source.hasOwnProperty(i)) {
          destination[i] = source[i];
        }
      }
    } else {
      destination = source;
    }
    return destination;
  },
  /**
   * Add event dispatcher to any DOM object
   * @param {string|HTMLElement} target HTML element or object 'id' this listener assigns to
   * @param {string} eventName name (i.e. 'load','click','mouseover')
   * @param {function|object} subscriber object or callback recieves event
   * @param {string} subMethod name of subscriber object that calls on event firing
   * @return {boolean} success
   */
  on: function(target, eventName, subscriber, subMethod) {
    eventName = eventName.toLowerCase();
    if (typeof(target)=='string') {
      var v, evs, subs;
      if(!subMethod) subMethod='on'+eventName;

      if (typeof(subscriber)=="function") {
        var subscriberObj={id:jsa.getUID('?')};
        subscriberObj[subMethod]=subscriber;
        subscriber=subscriberObj;
      } else {
        if(!subscriber[subMethod]) {
          jsa.console.error('jsa.on: subscriber object has no event handler '+subMethod);
          return false;
        }
        if (!subscriber.id) {
          subscriber.id=jsa.getUID('?');
        }
      }
      subs = jsa.subscribers[target];
      if (!subs) {
        subs = jsa.subscribers[target] = {};
      }
      evs = subs[subscriber.id];
      if (!evs) {
        evs = subs[subscriber.id] = {};
      }
      v = evs[eventName];
      if (!v) {
        v = evs[eventName] = [subscriber, subMethod];
      } else {
        jsa.console.warn("jsa.on: Resubscribe for "+eventName+" rejected");
      }
      return true;

    } else {
      if (!!target.addEventListener) {
        target.addEventListener(eventName, subscriber, true);
      } else {
        if (!!target.attachEvent) {
          target.attachEvent('on' + eventName, subscriber);
        }
      }
      return
    }
  },
  /**
   * Send message to all subscribers for events from the published object
   * @param {string} id of the publisher
   * @param {string} event name
   * @param {object} arguments passing to the subscribers callback functions
   */
  emit: function(pubId, eventName, eargs) {
    var sObjName, evs, subs = jsa.subscribers[pubId], v;
    if (!!subs) {
      for (sObjName in subs) {
        evs = subs[sObjName];
        v = evs[eventName];
        if (!!v) {
          try {
            var doRemoveSubscriber=(v[0])[v[1]](eargs);
            if(doRemoveSubscriber) {
              delete (evs[eventName]);
              /*if(evs.length==0) {
                delete(subs[sObjName]);
                if(subs.length==0) {
                  delete (jsa.subscribers[pubId]);
                }
              }*/
            }
          } catch (e) {
            jsa.console.error("jsa.emit: trying to execute unhandled subscriber " + sObjName + " for event " + eventName);
            debugger;
          }

          // jsa.run({_:v[0],f:v[1],args:eargs})
        }
      }
    }
  },

  eventEmitter: function(e) {
    var t = e.type, el = (e.target || e.srcElement), id = el.getAttribute('jsa_id');
    while ((id == null) && (el.nodeName != 'BODY') && (el.nodeName != 'HTML')) {
      el = el.parentElement;
      id = el.getAttribute('jsa_id');
    }
    if (id != null) {
      jsa.emit(id, e.type, e);
    }
  },


  /**
   * Loads js file
   * @param {string} jsPath to the loading script
   * @param {string} name of the module
   * @param {Object} doNext Actions runs after loading module if success or fail
   * @param {(string|Array)=} doNext.fail Action that runs on fail the loading
   * @param {(string|Array)=} doNext.run Action that runs on successful loading
   * @return {Object} loader record
   */
  loadJS: function(jsPath, moduleName, onload,onfail) {
    var s, doc = jsa.doc, jsElement = doc.createElement("script"), loader;
    if (!moduleName) {
      moduleName = jsPath;
    }
    jsElement.type = 'text/javascript';
    jsElement.onload = jsElement.onreadystatechange = function() {
      /** @this {Element} */
      if (loader.loading && (!this.readyState || this.readyState == "loaded" || this.readyState == "complete")) {
        this.onreadystatechange = this.onload = "";
        loader.loading = 0;
        loader.success = 1;
        if (!!loader.onload) {
          loader.onload(loader);
        }
        jsa.emit(loader.id,'load',{success:1});
      }
    };
    jsElement.onerror=function() {
      loader.loading=0;
      loader.success = 0;
      if (!!loader.onfail) {
        loader.onfail(loader);
      }
      jsa.emit(loader.id,'load',{success:0});
    }
    doc.getElementsByTagName("head")[0].appendChild(jsElement);
    s = jsPath + ((jsa.UNCACHEABLE_URLS) ? ((jsPath.indexOf('?') + 1) ? '&' : '?') + '~=' + Math.random() : "");
    jsElement.src = s;

    /** @this {Element} */
    loader = jsa.moduleLoaders[moduleName] = {
      id:jsa.getUID('loader'),
      moduleName: moduleName,
      jsDOMElement: jsElement,
      jsPath: jsPath,
      loading: 1,
      success: 0,
      onfail:onfail,
      onload:onload
    };
    return loader;
  },
  getUID: function(prefix) {
    return (prefix || "id") + (jsa.lastUID++);
  },
  /**
   * @param {String} tpl html template with {} expressions
   * @param {Object=} scopeObject object that provide its vars or methods
   */
  parsedHTML: function(tpl, scopeObject) {
    return tpl.replace(/\{([^}]+)\}/g, function(j, i) {
      /** @ignore - google closure warns about using keyword with() */
      with (scopeObject) {
        try {
          return eval('(' + i + ')');
        } catch (x) {
          return "{" + i + " " + x.message + "}";
        }
      }
    });
  },
  /**
   * @param {String} id name of the tag, i.e. div
   * @param {Object=} attrs list of tag attributes
   * @param {HTMLElement=} into HTML element to put newly created element inside
   * @param {String=} tpl html template
   * @param {Object=} scopeObject object that provide its vars or methods. If object is none the tpl kept unparsed
   * @returns undefined
   */
  createDiv: function(id, attrs, into, tpl, scopeObject) {
    var s, i, j, c = ((!!into) ? into.ownerDocument : jsa.doc).createElement('div');
    c.setAttribute('id', id);
    if (attrs) {
      for (i in attrs) {
        s = attrs[i];
        if (i == 'style') {
          for (j in s) {
            c.style[j] = s[j];
          }
        } else {
          c.setAttribute(i, s);
        }
      }
    }
    if (!!tpl) {
      c.innerHTML = (!scopeObject) ? tpl : jsa.parsedHTML(tpl, scopeObject);
    }
    if (!!into) {
      into.appendChild(c);
    }
    return c;
  },

  registerWindowFrame: function(window, frameName) {
    if (!jsa.frames[frameName]) {
      return new jsa.Frame(window, frameName);
    } else {
      jsa.console.error("jsa.registerWindowFrame: repeated resistration of frame " + frameName);
      return false;
    }
  },

  put:function(a) {
    var ctrl, vm, clsName = '', classDef, nothing = false;
    vm = a.vm;
    if (!a.owner) {
      a.owner = a.jsf;
    }
    if (!vm) {
      if (DEBUG) {
        jsa.console.error('jsa.put called without .vm parameter');
      }
      return nothing;
    }
    clsName = a.vm.clsName;
    if (!clsName) {
      if (DEBUG) {
        jsa.console.error('Cannot put control without .clsName attribute of ViewModel');
      }
      return nothing;
    }


//    ЗДЕСЬ добавить зарегистрированные контролы!
//    сначала сделать их регистрацию

    classDef = jsa.classes [clsName];
    if (!!classDef) {
      ctrl = new (classDef)();
      ctrl.put(a, 1); // 1 - means to do rearrange after create
      return ctrl;
    } else {
      if (DEBUG) {
        jsa.console.error('Undefined class jsa.' + clsName);
      }
      return nothing;
    }
  }
};


jsa.Frame = function(window, name) {
  this.jsa = window.top.jsa;
  this.name = name;
  this.win = window;
  this.doc = window.document;
  this.jsa.frames[name] = this;
  this.ownedObjects = {};

  jsa.on(this.doc, 'selectstart', function(e) {
    if ((e.target || e.srcElement).getAttribute('selectable') === null) {
      e.cancelBubble = true;
      return false;
    }
    return true;
  });



  jsa.on(this.doc, 'mousedown', jsa.eventPublisher);
  jsa.on(this.doc, 'mouseup', jsa.eventPublisher);
  //jsa.on(me.doc,'mousemove', jsa.eventPublisher);
  /*
   function(e){
   var srcId=e.srcElement.getAttribute('id');
   if(!!srcId) {
   jsa.console.log('Pub event mouseDown from '+srcId);
   jsa.pub(srcId,'mouseDown',e);
   }
   });
   */
};

/** @class jsa.Frame */
jsa.Frame.prototype.find = function(id) {
  /** @this jsa.Frame */
  return this.doc.getElementById(id);
};

jsa.Frame.prototype.run=function(act) {
  /** @this jsa.Frame */
  act.jsf = this;
  return jsa.run(act);
};


/** @class jsa.Console */
jsa.Console = function() {};

jsa.Console.prototype = {
  targetWindow:false,
  container:false,
  init: function(targetWindow, container){this.targetWindow=targetWindow; this.container=container;},
  log: function() {this.addLog(arguments, 1);},
  info: function() {this.addLog(arguments, 1);},
  warn: function() {this.addLog(arguments, 2);},
  error: function() {this.addLog(arguments, 3);},
  dump: function(o,deep) {
    var i, s = "", c = 0;
    if(!deep) {deep=0;}
    if(deep>4) {
      return '[[..]]';
    }

    if (typeof(o)=="object") {
      if (o instanceof Array) {
        for (i in o) {
          c++;
          if(c>1) s+=', ';
          if (c > 20) {
            s += "[...]";
            break;
          }
          s += this.dump(o[i],deep+1);
        }
        s='['+s+']';
      } else {
        for (i in o) {
          c++;
          if(c>1) s+=', ';
          if (c > 20) {
            s += "[...]";
            break;
          }
          s += "<b>" + i + "</b>:" + this.dump(o[i],deep+1) ;
        }
        s='{[<i>'+s.constructor.name+'</i>]'+s+'}';
      }
    } else {
      if (typeof (o)=='string') {
        s+='<pre>"'+o+'"</pre>';
      } else {
        s+=o;
      }
    }
    return s;
  },
  addLog: function(a, mode) {
    var e,s;
    s=this.dump(a);
    if(this.targetWindow && this.container) {
      e=document.createElement('div');
      e.style.border='solid 1px';
      e.innerHTML=s;
      this.container.appendChild(e);
    } else {
      switch(mode) {
        case 2: console.warn(a);break;
        case 3: console.error(a);break;
        default: console.log(a);
      }
    }
  }
};

jsa.console = new jsa.Console();


/** @class jsa.Control */
jsa.Control = function(){};
/**
 * Factory method for all Controls
 * @param {object}      a arguments
 * @param {Object}      a.target target control to put the newest control inside
 * @param {Number}      a.x x coordinate
 * @param {Number}      a.y y coordinate
 * @param {object}      a.owner owner the jsf container that has ownedObjects{}
 * @param {jsa.Frame}   a.jsf environment
 * @param {HTMLElement} a.he htmlElement that will containing created control
 * @param {Number}		isFirst means 1=first control that will call .arrangeKids
 **/
jsa.Control.prototype.put = function(a, isFirst) {
  var me = this, viewModel = a.vm, htmlTag, element, doc, parentCtrl = a.target, s, j;
  if (!a.jsf) {
    if(!me.jsf) {
      if (!!parentCtrl.jsf) {
        me.jsf = parentCtrl.jsf;
      }
      else {
        jsa.console.info('jsa.Control.put({}) without jsf');
        return;
      }
    }
  } else {
    me.jsf=a.jsf;
  }

  if(!a.owner) {
    if (!parentCtrl.owner){
      me.owner=me.jsf;
    } else {
      me.owner=parentCtrl.owner;
    }
  } else {
    me.owner=a.owner;
  }

  htmlTag = viewModel.tag || 'div';

  if (!me.jsf.doc) {
    jsa.console.info('jsa.Control.put({jsf}) has no document reference in attribute .doc');
    return;
  }
  doc = me.jsf.doc;
  me.element = element = ((!a.he) ? doc : a.he.ownerDocument).createElement(htmlTag);
  me.x = a.x || 0;
  me.y = a.y || 0;
  me.isVisible = a.isVisible || true;
  me.id = viewModel.id || jsa.getUID(me.clsName);
  me.element.setAttribute('id', me.id);
  me.element.setAttribute('jsa_id', me.id);

  if (!!me.owner) {
    me.owner.ownedObjects[me.id] = me;
  } else {
    if (DEBUG) {
      jsa.console.warn("Created " + (me.clsName) + " without reference to owner. It means memory leaks");
    }
  }
  me.viewModel = viewModel;
  me.parentCtrl = parentCtrl;
  me.dataProvider = a.dataProvider;
  me.minWidth = viewModel.minWidth || 50;
  me.minHeight = viewModel.minHeight || 40;
  me.borderSize = viewModel.borderSize || 0;
  me.padding = viewModel.padding || 0;
  me.width = viewModel.width || 200;
  me.height = viewModel.height || 200;
  me.kids = [];
  if (!!viewModel.html) {
    element.innerHTML = viewModel.html;
  }
  if (!!viewModel.thtml) {
    element.innerHTML = jsa.parsedHTML(viewModel.thtml, me);
  }
  if (!!(s = viewModel.a)) {
    for (j in s) {
      element.setAttribute(j, s[j]);
    }
  }
  if (!!(s = viewModel.s)) {
    for (j in s) {
      element.style[j] = s[j];
    }
  }

  if ((!parentCtrl) && (!a.he)) {
    me.topHtmlContainer = (doc.compatMode == 'CSS1Compat') ? doc.documentElement : doc.body;
  }
};

jsa.Control.prototype.destroy=function() {
  var i, c;
  for (i in this.ownedObjects) {
    c = this.ownedObjects[i];
    c.destroy();
    delete this.ownedObjects[i];
  }
  if (c.element) {

  }
};

jsa.Control.prototype.setPosSizeVisible=function() {
  var e = this.element, es, offset = (this.borderSize + this.padding) * 2;
  if (e) {
    es = e.style;
    if ((this.w <= 0) || (this.h <= 0)){
      this.isVisible = 0;
    }
    if (!this.isVisible) {
      es.display = 'none';
    } else {
      es.position = 'absolute';
      es.left = this.x + 'px';
      es.top = this.y + 'px';
      es.width = (this.w - offset) + 'px';
      es.height = (this.h - offset) + 'px';
      es.display = 'block';
    }
  }
};

(function(){
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
})();



(function(){
var loader=jsa.loadJS("../src/DockPanel.js","jsa.DockPanel");
jsa.on('module:'+loader.moduleName,"ready",{onready:function(args){
  jsa.worker=new jsa.scheduler.Worker();
  jsa.worker.intervalHandler=window.setInterval(function(){
    jsa.worker.loopAll();},1000);
  //jsa.emit('worker','ready');
  return true;
}});
})();

