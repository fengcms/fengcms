$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href =	FengAdmin.executionURL('modulemanage',data);
			}else if(data.status=="e"){
				alert("模型名称或数据表名称已存在！")
			}else{
				location.href =	FengAdmin.executionURL('modulemanage',data);

			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});