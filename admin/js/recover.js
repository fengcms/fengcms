$(function(){
	var form=$(".form");
	var project=new URL(form.attr("action"),window.location.href).searchParams.get("project") || "";
	var data=form.Validform({
		callback:function(data){
			if(data.status=="y"){
				location.href =FengAdmin.executionURL('recover',data,{includeId:false,project:data.project || project});
			}else if(data.status=="a"){
				alert('请选择记录！');
			}else if(data.status=="n"){
				location.href =FengAdmin.executionURL('recover',data,{includeId:false,project:data.project || project});
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
		location.href ="?controller=recover&project="+encodeURIComponent(project)+"&field="+encodeURIComponent($("#field").val())+"&title="+encodeURIComponent($("#title").val());
	});
	$(".form").keypress(function(e){ if (e.which == 13){ return false; }});
});