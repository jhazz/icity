/* global jsa */
jsa.module({
name:'jsa.DockPanel',
c:{SPLITTER_MODE_STRETCH:1, SPLITTER_MODE_RESIZE_DOCK:2,SPLITTER_MODE_RESIZE_CONTROL:3},
init:function (module){
(jsa.DockPanel = function() {}).prototype = new jsa.Control();

jsa.DockPanel.prototype.superClass = jsa.Control.prototype;

jsa.DockPanel.prototype.put = function(a, doArrangeAfterPut) {
  /* viewModel is a JSON template. {t:'div',width:100,position:'absolute',
  height:200,idp:'idprefix',before:"evalCodeBeforeChild",
  _:[t:'ul',a:{type:'circle'}],after:"evalCodeAfterCreate"}
  */
  var me = this,
    viewModel = a.vm,
    dataProvider = a.dp,
    htmlContainer = a.he,
    parentCtrl = a.target,
    childElementDef,
    s, j,
    doc = a.jsf.doc,
    element;

  me.side = viewModel.side;
  me.superClass.put.call(this, a);
  element = me.element;
  me.size();
  if (!!(s = viewModel._)) {
    for (j in s) { // array of child element definitions
      childElementDef = s[j];
      // TODO: не хочу использовать Path и его выстраивание контролами.
      // Хочется позвать dataProvider и сообщить, что я вхожу в дочерние узлы, чтобы он сам расставил бинды
      jsa.put({owner: a.jsf, jsf: a.jsf, vm: childElementDef, dp: dataProvider, he: element, target: me});
    }
  }

  if (!!parentCtrl) {
    parentCtrl.kids.push(me);
    if (doArrangeAfterPut) {
      parentCtrl.arrangeKids();
    }
  }
  if (!!htmlContainer) {
    htmlContainer.appendChild(element);
  } else {
    doc.body.appendChild(element);
    me.setPosSizeVisible();
    jsa.on(a.jsf.win, 'resize', function() {
      jsa.run({aidAfter: me.id + "winresize", f: function(act) {
          var t = act.target;
          jsa.console.log("DockPanel " + t.id + " rearranging");
          t.size();
          t.setPosSizeVisible();
          t.arrangeKids();
        }}, me);
    });
  }
};


/**
 * updates (this.w, this.h) <= (this.width, this.height) that described in percents
 * If any dimension had changed set this.sizeChanged to true
 * Note: this.viewModel contains initial size
 **/
jsa.DockPanel.prototype.size = function() {
  var w = this.w, h = this.h, l, htmlContainer = this.htmlContainer, s, parentWidth,
    parentHeight, parentCtrl = this.parentCtrl;

  s = this.width;
  if (isFinite(s)) {
    this.w = s;
  } else {
    if (s.charAt((l = s.length - 1)) == '%') {
      if (!parentCtrl) {
        if (!htmlContainer) {
          parentWidth = this.topHtmlContainer.clientWidth;
        } else {
          parentWidth = htmlContainer.clientWidth;
        }
      } else {
        parentWidth = parentCtrl.w - (parentCtrl.borderSize + parentCtrl.padding) * 2;
      }
      this.w = parseInt(s.substr(0, l)) * parentWidth / 100;
    } else {
      this.w = parseInt(s);
    }
  }

  s = this.height;
  if (isFinite(s)) {
    this.h = s;
  } else {
    if (s.charAt((l = s.length - 1)) == '%') {
      if (!parentCtrl) {
        if (!htmlContainer) {
          parentHeight = this.topHtmlContainer.clientHeight;
        } else {
          parentHeight = htmlContainer.clientHeight;
        }
      } else {
        parentHeight = parentCtrl.h - (parentCtrl.borderSize + parentCtrl.padding) * 2;
      }
      this.h = parseInt(s.substr(0, l)) * parentHeight / 100;
    } else {
      this.h = parseInt(s);
    }
  }
  this.sizeChanged = ((w != this.w) || (h != this.h));
};

/**
 *
 * @param {Array} dockSet array of docking controls
 * @param {Object} boundary view limits
 * @param {Number} spOn allow add splitters
 * @param {Number} ss splitter size in pixels
 */
jsa.DockPanel.prototype._arrangeDockSet = function(dockSet, boundary, spOn, ss) {
  var me = this, doc = me.element.ownerDocument, justadded, needSplitter, mul, ws, j, stackPos, a, l, isLast, side, isVertical,
    amount, maxThick, tx, ty, tw, th, tv, sp;
  ss = ss || 5;
  if (!dockSet) {
    return;
  }

  l = dockSet.length;
  if (l > 1) {
    //debugger;
  }
  for (j = 0; j < l; j++) {
    a = dockSet[j];
    if (!j) {
      side = a.side;
      isVertical = (side == 'W') || (side == 'E') || (side == 'M');
      amount = 0;
      maxThick = 0;
    }
    if (isVertical) {
      if (a.width > maxThick) {
        maxThick = a.width;
      }
      amount += a.height;
    } else {
      if (a.height > maxThick) {
        maxThick = a.height;
      }
      amount += a.width;
    }
  }
  // window size
  ws = (isVertical) ? boundary.vy2 - boundary.vy1 : boundary.vx2 - boundary.vx1;
  mul = (ws < 1) ? 1 : amount / (ws - (l - 1) * ss);
  stackPos = (isVertical) ? boundary.vy1 : boundary.vx1;
  for (j = 0; j < l; j++) {
    a = dockSet[j];
    isLast = (j == (l - 1));
    needSplitter = (!isLast) && (spOn);
    if ((a.isVisible = (ws > 0))) {
      if (isVertical) {
        a.h = (isLast) ? ws : Math.floor(a.height / mul);
        if (a.h < a.minHeight) {
          a.h = a.minHeight;
        }
        a.w = maxThick;
        a.y = stackPos;
        stackPos += a.h + ss;
        ws -= a.h + ss;
      } else {
        a.x = stackPos;
        a.w = (isLast) ? ws : Math.floor(a.width / mul);
        if (a.w < a.minWidth) {
          a.w = a.minWidth;
        }
        a.h = maxThick;
        stackPos += a.w + ss;
        ws -= a.w + ss;
      }
      switch (side) {
        case 'N': // North - top
          a.y = boundary.vy1;
          break;
        case 'S':
          a.y = boundary.vy2 - maxThick;
          break;
        case 'E': // East - right
          a.x = boundary.vx2 - maxThick;
          break;
        case 'M':
          a.w = boundary.vx2 - boundary.vx1; // NO BREAK!
        case 'W':
          a.x = boundary.vx1;
      }
      if (needSplitter) {
        if (isVertical) {
          tx = a.x;
          ty = a.y + a.h;
          tw = a.w;
          th = ss;
          tv = 1;
        } else {
          tx = a.x + a.w;
          ty = a.y;
          tw = ss;
          th = a.h;
          tv = 0;
        }
      }
    } else {
      needSplitter = 0;
      jsa.console.info('not isVisible ' + a.viewModel.html + " w=" + a.w + ' h=' + a.h);
    }

    a.arrangeKids();
    a.setPosSizeVisible();
    if (needSplitter) {
      if (!(sp = a.stretchSplitter)) {
        jsa.console.info("jsa.put splitter");
        sp = jsa.put({
          target: me,
          jsf: me.jsf,
          using: 1,
          x: tx, // will be re-calculated during rearrange
          y: ty,
          mode: jsa.c.SPLITTER_MODE_STRETCH,
          dockSetPos: j,
          vm: {
            clsName: 'Splitter',
            width: tw,
            height: th,
            s: {
              backgroundColor: 'red',
              cursor: tv ? 'row-resize' : 'col-resize'
            }
          }
        });
        if (!sp) {
          jsa.console.error("jsa.put splitter returned nothing");
        }
        a.stretchSplitter = sp;
      } else {
        sp.x = tx;
        sp.y = ty;
      }
      if (sp) {
        jsa.console.info("splitter " + sp.id + " resized");
        sp.size();
        sp.setPosSizeVisible();
      }
    }
  }
  if (a.isVisible){
    switch (side) {
      case 'N':
        boundary.vy1 += maxThick + ss;
        break;
      case 'E':
        boundary.vx2 -= maxThick + ss;
        break;
      case 'W':
        boundary.vx1 += maxThick + ss;
        break;
      case 'S':
        boundary.vy2 -= maxThick + ss;
    }// M should be only last! Works like 'W'
  }
};

/**
 * Clears all neighbour dockSets inside docked controls
 **/
jsa.DockPanel.prototype.flushArrangedDock = function() {
  var me = this, i;
  for (i in me.dockSets) {
    delete me.dockSets[i];
  }
}


jsa.DockPanel.prototype.arrangeKids = function() {
  var me = this, kidCount, i, dockSet, dockSetStarted = 0, dockedControl, tmp, sp,
    boundary = {vx1: me.padding, vy1: me.padding, vx2: me.w - me.padding * 2, vy2: me.h - me.padding * 2};

//	if(!me.dockSets){
  me.dockSets = {};
  //}
  if (!!me.stretchSplitters) {
    for (i in me.stretchSplitters) {
      me.stretchSplitters[i].using = 0;
    }
  } else {
    me.stretchSplitters = [];
  }

  kidCount = me.kids.length;
  for (i = 0; i < kidCount; i++) {
    dockedControl = me.kids[i];
    if (dockedControl.side !== undefined) {
      if (dockedControl.side != 'A') { // not Attached to previous docked panel
        if (dockSetStarted) {
          // arrange previous collected dockSet
          me._arrangeDockSet(dockSet, boundary, 1, 5);
        }

        // check this control inside previously generated dockSets[control.id]
        if (!(dockSet = me.dockSets[dockedControl.id])) {
          dockSet = me.dockSets[dockedControl.id] = [dockedControl];
          dockSetStarted = 1;
        }
      } else {
        dockSet.push(dockedControl);
        me.dockSets[dockedControl.id] = dockSet;
      }
    }
  }
  if (dockSetStarted) {
    me._arrangeDockSet(dockSet, boundary, 1, 5);
  }

  if (!!me.stretchSplitters) {
    for (i = me.stretchSplitters.length - 1; i >= 0; i--) {
      sp = me.stretchSplitters[i];
      if (!sp.using) {

        sp.destroy();
        /*
         sp.htmlElement.parentNode.removeChild(sp.htmlElement);
         delete sp.htmlElement;
         if ((!!sp.control)&&(sp.control.stretchSplitter)) delete sp.control.stretchSplitters;
         delete me.stretchSplitters[i];
         */
      }
    }
  }
};

jsa.DockPanel.prototype.anchor = function(id) {
  return "[[" + id + "_" + jsa.getUID() + "]]";
};

}});