$(function(){

	var showmsg=function(msg){ alert(msg); }

	data=$(".form").Validform({
		tiptype:1,
		callback:function(data){
			if(data.status=="y"){
				location.href =	"?controller=tplmanage&operate=execution&re=1&project="+data.project;
			}else if(data.status=="e"){
				alert("文件已存在！")
			}else{
				location.href =	"?controller=tplmanage&operate=execution&re=0&project="+data.project;

			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});