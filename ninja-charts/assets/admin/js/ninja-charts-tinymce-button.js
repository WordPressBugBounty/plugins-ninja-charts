tinymce.create("tinymce.plugins.ninja_charts",{init:function(n,t){n.addButton("ninja_charts",{title:"Add Ninja Charts Shortcode",cmd:"ninja_charts_action",image:t.slice(0,t.length-2)+"img/icon_small.png"}),n.addCommand("ninja_charts_action",(function(){n.windowManager.open({title:window.ninja_charts_tiny_mce.title,body:[{type:"listbox",name:"ninja_charts_shortcode",label:window.ninja_charts_tiny_mce.label,values:window.ninja_charts_tiny_mce.charts}],width:768,height:100,onsubmit:function(t){if(!t.data.ninja_charts_shortcode)return alert(window.ninja_charts_tiny_mce.select_error),!1;n.insertContent('[ninja_charts id="'+t.data.ninja_charts_shortcode+'"]')},buttons:[{text:window.ninja_charts_tiny_mce.insert_text,subtype:"primary",onclick:"submit"}]},{tinymce})}))}}),tinymce.PluginManager.add("ninja_charts",tinymce.plugins.ninja_charts);