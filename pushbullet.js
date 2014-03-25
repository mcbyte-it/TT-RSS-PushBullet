function shareArticleToPushbullet(id, btn) {
	try {
		var query = "?op=pluginhandler&plugin=pushbullet&method=getInfo&id=" + param_escape(id);

		var d = new Date();
	        var ts = d.getTime();

		notify_progress("PushBullet to …", true);
		new Ajax.Request("backend.php",	{
			parameters: query,
			onSuccess: function(transport) {
				var ti = JSON.parse(transport.responseText);
				if (ti.status=="1") {
					notify_info("Sent PushBullet:<br/><em>" + ti.title + "</em>");
				
					btn.src='plugins/pushbullet/pushbullet_green.png';
					btn.title='Sent to PushBullet';
				} else {
					alert("Error sending PushBullet!\n"+ti.status+"");
				}
			}
		});

	} catch (e) {
		exception_error("PushBulletArticle", e);
	}
}
