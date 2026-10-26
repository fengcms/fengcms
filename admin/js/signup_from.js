$(document).ready(function() {
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href =FengAdmin.executionURL('signup',data,{includeId:false});
			}else{
				location.href =FengAdmin.executionURL('signup',data,{includeId:false});
			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});