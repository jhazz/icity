/*global jsa, ActiveXObject*/
var FX={
init:function(){
    (jsa.DirViewer=function(){}).prototype = new jsa.Control();
    jsa.DirViewer.prototype.clsName='jsa.DirViewer';
    jsa.DirViewer.prototype.superClass=jsa.Control.prototype;
    jsa.DirViewer.prototype.put=function(a){
    	
        jsa.console.log('DirViewer.put called',a);
		var nr=jsa.DirViewer.prototype.nativeReader;
		if(!nr){
			nr=jsa.DirViewer.prototype.nativeReader={};
			if(!!document.all){
				try {
					nr.axFSO=new ActiveXObject("Scripting.FileSystemObject");
					nr.type='ActiveX_FSO';
					jsa.console.log('Scripting.FileSystemObject Inited');
				}
				catch(e){
					jsa.console.log("Не могу создать ActiveXObject(Scripting.FileSystemObject) "+e.message);
	            }
			}
		}
		// либо a.he дан либо a.target.element
		debugger;
        this.superClass.put.call(this,a);
	//	debugger;
        this.parentCtrl.element.appendChild(this.element);
    };
    
    
    
	FX.dataProvider1=new jsa.GridDataProvider();
	FX.dataProvider1.pasteData([2,3] /*"a1;c2*/,[[10,"first row",2012],[20,"second row",2013]]);

	FX.vmodel1={
		clsName:'DockPanel',
		padding:4, borderSize:2,
		width:'100%',
		height:'100%',
		id:'ModelMainFrame',
		s:{padding:'4px',border:'2px solid',background:'#888070'},
		_:[{
			clsName:'DockPanel',
			s:{border:'1px outset',background:'#f8f0e0',padding:'2px'},
			borderSize:1, padding:2,height:20,
			id:'inframe1Menu',
			html:'This is North',
			side:'N'
		},{
			clsName:'DockPanel',
			s:{border:'1px outset',background:'#e4dad0',padding:'2px'},
			borderSize:1, padding:2,
			html:'This is East',
			height:50,
			width:300,
			side:'E',
			_:[{
				clsName:'DockPanel',
				s:{border:'1px outset',background:'#c8f0e0',padding:'2px'},
				borderSize:1, padding:2,
				html:'This is internal North',
				side:'N'
			},{
				clsName:'DockPanel',
                id:'inframe1Dirview1',
				s:{border:'1px outset',background:'#c8f0e0',padding:'2px'},
				borderSize:1, padding:2,
				width:60,
				height:10,
				html:'This is internal Middle',
				side:'M'
			},{
				clsName:'DockPanel',
				s:{border:'1px outset',background:'#caf2e0',padding:'2px'},
				borderSize:1, padding:2,
				width:50,
				height:10,
				html:'This is internal East2',
				side:'A'
			}
				
			]
		},{
			clsName:'DockPanel',
			s:{border:'1px outset',background:'#fffaf6',padding:'2px'},
			borderSize:1, padding:2,
			html:'This is Middle',
			height:20,
			side:'M'
		}]
	};
	

	debugger;
	inframe1.run({c:jsa.c.ACTION_JSA_PUT, vm:FX.vmodel1, dp:FX.dataProvider1, next:{
        f:function(x){
			// после того как лейаут построен - внедряем директории , inframe1Dirview1 станет parentControl
			//debugger;
			jsa.run({c:jsa.c.ACTION_JSA_PUT,vm:{clsName:'DirViewer',id:'flashInbox1'},next:{
					f:function(y){
						jsa.console.log('DirViewer.put done',y);
					}
			}},inframe1.ownedObjects['inframe1Dirview1']);

//                FX.serverInbox=new jsa.Control({name:'Inbox папка на сервере'});
			inframe1.ownedObjects['inframe1Menu'].element.innerHTML="<a href='javascript:;' onclick='showSettings()'>Настройки</a> &nbsp;"
			+"<a href='javascript:;' onclick='FX.loadAllFolders()'>(1) Прочитать списки файлов</a>";
            
        }
    }});
	
    
    
},
putText:function(s,targetContainer){
  if (!s)return;
  var x=document.createElement("div");
  x.innerHTML=s;
  targetContainer.appendChild(x);

  try{
  x.style.border="1px solid #0";
  }catch(e){}
  x.style.width="20%";
  x.style.display="inline";
},

loadAllFolders:function(){
	FX.initParamsFromInput();
	debugger;
	FX.loadDirList(FX.flashInbox,1);
	FX.loadDirList(FX.serverInbox,1);
	FX.displayListToDiv(FX.flashInbox,document.getElementById('folderFlashInbox'));
	FX.displayListToDiv(FX.serverInbox,document.getElementById('folderServerInbox'));
},
displayListToDiv:function(aFolderData,targetDiv){
	var e,v;
	targetDiv.innerHTML=aFolderData.path+"<br/>";
	for(e in aFolderData.items){
		v=aFolderData.items[e];
		if (v.t==1) FX.putText('['+v.n+']',targetDiv);
		if (v.t==2) FX.putText("<a title='"+v.p+"'>"+v.n+"</a>",targetDiv);
	}
},
initParamsFromInput:function(){
	FX.flashInbox.path=document.getElementById('pathFlashInbox').value;
	FX.serverInbox.path=document.getElementById('pathServerInbox').value;
},


loadDirList:function(aFolderData,isRecursive){
	aFolderData.items=[];
	try{
		var oFld = FX.oFS.GetFolder(aFolderData.path); //.ParentFolder.ParentFolder;
	}catch(e){
		alert(e.description);
		return;
	}
	FX.recursiveFolder (oFld,'',
		function (item,subFolder){
			aFolderData.items.push({t:1,n:item.Name,sp:subFolder,p:item.Path});
			//FX.putText(item.Name+ "/",aFolderData); //Показать папки
		},
		function (item,subFolder){
			//FX.putText("file:"+ item.Name,aFolderData); //Показать файлы
			aFolderData.items.push({t:2,n:item.Name,sp:subFolder,p:item.Path});
		},
		true //isRecursive
	);
},

recursiveFolder:function(oFld,subFolder,callbackFolder,callbackFile,isRecursive){
	var enFolders = new Enumerator(oFld.SubFolders);
	var enFiles = new Enumerator(oFld.Files);

	for (;!enFiles.atEnd(); enFiles.moveNext()) callbackFile(enFiles.item(),subFolder);

	for ( ; !enFolders.atEnd(); enFolders.moveNext()) {
		var item=enFolders.item();
		callbackFolder(item,subFolder);
		if(isRecursive){
			var inFolders = new Enumerator(oFld.SubFolders); //Снова коллекция папок
			var sf=inFolders.item();
			arguments.callee(sf,"/"+sf.Name,callbackFolder,callbackFile,isRecursive); //Рекурсия в подпапку
		}
	}
}

};

