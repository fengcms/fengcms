$(document).ready(function() {
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href ="?controller=classify&operate=execution&type=save&re=1&id="+data.id;
			}else{
				location.href ="?controller=classify&operate=execution&type=save&re=0&id="+data.id;
			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});

	$("#project").on("change",function(){
		$("#classify").load('?controller=classify&operate=classify&p='+$("#project").val());
		$("#channel_template").val("/"+$("#project").val()+".html");
		$("#classify_template").val("/"+$("#project").val()+"_class.html");
		$("#content_template").val("/"+$("#project").val()+"_content.html");
	});

	$(".type").click(function(){
		if($('input[name="type"]:checked').val()=="module"){
			$("#type_content").load('?controller=classify&operate=type&t=module');
			//$(".type_tps").show();
		}else if($('input[name="type"]:checked').val()=="url"){
			$("#type_content").load('?controller=classify&operate=type&t=url');
			//$(".type_tps").show();
		}else if($('input[name="type"]:checked').val()=="single"){
			$("#type_content").load('?controller=classify&operate=type&t=single');
			$("#project").val("single");
			//$(".type_tps").hide();
		}
	});

	$("#genre").on("change",function(){
		$("#genre_content").load('?controller=classify&operate=genre&f='+$("#genre").val());
	});
});
