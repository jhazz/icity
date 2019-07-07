/* global jsa */
jsa.module({name:'jsa.Splitter',init:function (module){

(jsa.Splitter = function(){}).prototype = new jsa.Control();

jsa.Splitter.prototype.clsName ='jsa.Splitter';

jsa.Splitter.prototype.superClass = jsa.Control.prototype;

jsa.Splitter.prototype.put = function(a) {
  jsa.console.log('.Splitter.put called', a);
  this.superClass.put.call(this, a);
  this.mode = a.mode;
  this.stretchControl1 = a.stretchControl1;
  this.stretchControl2 = a.stretchControl2;
  // TODO add destroy publisher
//    jsa.sub(this.stretchControl1,'destroy',this,function(){
//      jsa.console.log('Control destroyed. So splitter should destroyed too');
//        })
  jsa.sub(this, 'mousedown', this, 'mousedown');
  jsa.sub(this, 'mouseup', this, 'mouseup');
  jsa.sub(this, 'mousemove', this, 'mousemove');

  this.parentCtrl.element.appendChild(this.element);
};
jsa.Splitter.prototype.mousedown = function(e) {
  jsa.console.log('Splitter mouse down');
};
jsa.Splitter.prototype.mouseup = function(e) {
  jsa.console.log('Splitter mouse up');
};
jsa.Splitter.prototype.mouseout = function(e) {
  jsa.console.log('Splitter mouse out');
};
jsa.Splitter.prototype.size = function() {
  var w = this.width, h = this.height;
  this.sizeChanged = ((w != this.w) || (h != this.h));
  if (this.sizeChanged) {
    this.w = w;
    this.h = h;
  }
};
}});