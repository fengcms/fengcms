$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href =	FengAdmin.executionURL('modulemanage',data,{type:'exc',includeId:false});
			}else if(data.status=="e"){
				alert("模型名称或数据表名称已存在！")
			}else{
				location.href =	FengAdmin.executionURL('modulemanage',data,{type:'exc',includeId:false});

			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});