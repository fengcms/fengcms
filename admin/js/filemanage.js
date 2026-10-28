$(function(){

	var showmsg=function(msg){ alert(msg); }

	data=$(".form").Validform({
		tiptype:1,
		callback:function(data){
			var result=data && data.responseJSON ? data.responseJSON : data;
			if(result.status=="y"){
				location.href =	FengAdmin.executionURL('tplmanage',result,{type:null,includeId:false});
			}else if(result.status=="e"){
				alert("文件已存在，请换一个名称。")
			}else{
				alert(result.message || "保存失败，请稍后重试。");

			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});
