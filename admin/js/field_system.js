$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href =	FengAdmin.executionURL('fieldmanage',data,{includeId:false});
			}else if(data.status=="e"){
				alert("您没有选择字段！")
			}else{
				location.href =	FengAdmin.executionURL('fieldmanage',data,{includeId:false});

			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});