$(function(){
	data=$(".form").Validform({
		callback:function(data){
			if(data.status=="y"){
				location.href ="?controller=recover&operate=execution&type=save&re=1&id="+data.id;
			}else if(data.status=="a"){
				alert('请选择记录！');
			}else if(data.status=="n"){
				location.href ="?controller=recover&operate=execution&type=save&re=0&id="+data.id;
			}
		}
	});
	$(".recovers"+","+".thorough").click(function(){
		$("#method").val($(this).attr("name"));
		$("#parameter").val($(this).attr("class"));
		data.ajaxPost();
		$.Hidemsg();
	});
	$("#search").click(function(){
		location.href ="?controller=recover&field="+$("#field").val()+"&title="+$("#title").val();
	});
	$(".form").keypress(function(e){ if (e.which == 13){ return false; }});
});