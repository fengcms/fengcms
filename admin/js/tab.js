$(document).ready(function(){
	//默认
	var winheight = $(window).height();
	var winwidth = $(window).width();
	$("#main").height(winheight-40);

	$(window).resize(function(){
		var winheight = $(window).height();
		var winwidth = $(window).width();
		$("#main").height(winheight-40);
	});
});

// JavaScript Document
function swap_tab(n)
{
 for(var i=1;i<=4;i++){
  var curC=document.getElementById("tab_"+i);
  var curB=document.getElementById("tab_t"+i);
  if(n==i){
   curC.style.display="block";
   curB.className="active"
  }else{
   curC.style.display="none";
   curB.className="normal"
  }
 }
} 



function checkAll() {
for (var j = 1; j <= 1000; j++) {
box = eval("document.checkboxform.record" + j); 
if (box.checked == false) box.checked = true;
   }
}

function uncheckAll() {
for (var j = 1; j <= 1000; j++) {
box = eval("document.checkboxform.record" + j); 
if (box.checked == true) box.checked = false;
   }
}

function switchAll() {
for (var j = 1; j <= 1000; j++) {
box = eval("document.checkboxform.record" + j); 
box.checked = !box.checked;
   }
}

function load(url){
	$(function(){
		$("#body").load(url);
	});
}

function link(id){
	$(function(){
		load($(id).attr("url"));
	});
}

function Gotonew(url){
	window.location = url;   //url为你要跳转的页面的url
}

window.onerror=function(){return true;} 