<?php

require 'PushBulletLib.class.php';

class pushbullet extends Plugin {
	private $host;
	
	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function about() {
		return array(0.1,
				"Push articles to PushBullet",
				"mcbyte");
	}
	
	function save() {

		$need_reload = true;
		
		$pushbullet_api = db_escape_string($_POST["pushbullet_api"]);
		$this->host->set($this, "pushbullet_api", $pushbullet_api);

		$pushbullet_sel_device = db_escape_string($_POST["pushbullet_sel_device"]);
		if (!empty($pushbullet_sel_device)) {
			$this->host->set($this, "pushbullet_sel_device", $pushbullet_sel_device);
			$need_reload = false;
		}
		
		if ($need_reload) {
			print "PREFS_NEED_RELOAD";
		} else {
			print __("PushBullet settings saved.");
		}
		
	}

	function api_version() {
		return 2;
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/pushbullet.js");
	}

	function hook_article_button($line) {
		$article_id = $line["id"];

		$rv = "<img src=\"plugins/pushbullet/pushbullet_gray.png\"
			class=\"tagsPic\" id=\"ocp$article_id\" style=\"cursor : pointer\"
			onclick=\"shareArticleToPushbullet($article_id, this)\"
			title='".__('PushBullet to')."'>";

		return $rv;
	}

	function getInfo() {
		//retrieve Data from the DB
		$id = db_escape_string($_REQUEST['id']);
		$result = db_query("SELECT title, link
				FROM ttrss_entries, ttrss_user_entries
				WHERE id = '$id' AND ref_id = id AND owner_uid = " .$_SESSION['uid']);
		
		if (db_num_rows($result) != 0) {
			$title = truncate_string(strip_tags(db_fetch_result($result, 0, 'title')),
					100, '...');
			$article_link = db_fetch_result($result, 0, 'link');
		}
		
		$pushbullet_api = $this->host->get($this, "pushbullet_api");
		$pushbullet_sel_device = $this->host->get($this, "pushbullet_sel_device");
		
		if (empty($pushbullet_api) || empty($pushbullet_sel_device)) {
			$status = 'PushBullet for Tiny Tiny RSS has not yet been configured, please check the Preferences.';
		} else {
			if (function_exists('curl_init')) {
				//Call Pushbullet API
				try {
					$p = new PushBulletLib($pushbullet_api);
					$status = $p->pushLink($pushbullet_sel_device, $title, $article_link);
				} catch (PushBulletException $e) {
					$status = $e->getMessage();
				}
			} else {
				$status = 'For the plugin to work you need to <strong>enable PHP extension CURL</strong>!';
			}
		}
		
		//Return information on article and status
		print json_encode(array(
			"title" => $title,
			"link" => $article_link,
			"id" => $id,
			"status" => $status
			));		
	}

	function hook_prefs_tab($args) {
	    //Add preferences pane
		if ($args != "prefPrefs") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__("PushBullet")."\">";

		print "<br/>";

        $pushbullet_api = $this->host->get($this, "pushbullet_api");
        $pushbullet_sel_device = $this->host->get($this, "pushbullet_sel_device");
		
		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
		if (this.validate()) {
			console.log(dojo.objectToQuery(this.getValues()));
			new Ajax.Request('backend.php', {
	parameters: dojo.objectToQuery(this.getValues()),
	onComplete: function(transport) {
		var msg = transport.responseText;
		if (msg == 'PREFS_NEED_RELOAD') {
			alert(__('PushBullet API key has been saved, Preferences will reload to show available devices to push to.'));
			window.location.reload();
		} else {
			notify_info(msg);
		}
	}
});
}
</script>";

print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"pushbullet\">";
print "<table width=\"100%\" class=\"prefPrefsList\">";

if (!function_exists('curl_init')) {
 print '<tr><td colspan="3" style="color:red;font-size:large">For the plugin to work you need to <strong>enable PHP extension CURL</strong>!</td></tr>';
}

    print "<tr><td width=\"20%\">".__("PushBullet API key")."</td>";
	print "<td width=\"30%\">Get you API key from your <a href=\"https://www.pushbullet.com/account\">PushBullet Account</a> page</td>";
	print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"pushbullet_api\" value=\"$pushbullet_api\"></td></tr>";

if (!empty($pushbullet_api)) {
    print "<tr><td width=\"20%\">".__("Push to this device")."</td>";
	print "<td width=\"30%\">Select a device to Push to</td>";
	print "<td class=\"prefValue\"><select name=\"pushbullet_sel_device\" dojoType=\"dijit.form.Select\" required=\"1\"><option>".__("-- Select device --")."</option>";

	try {
		$p = new PushBulletLib($pushbullet_api);
		$devices = $p->getMyDevices();
		foreach ($devices as $device) {
			$deviceId = $device['iden'];
			print "<option value=\"" . $deviceId . "\"";
			if ($pushbullet_sel_device == $deviceId) {
				print " selected=\"selected\"";
			}
			print ">" . $device['extras']['model'] . "</option>";
		}
	} catch (PushBulletException $e) {
		$e->getMessage();
	}
	print "</select></td></tr>";
}
	print "</table>";
	print "<button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Save")."</button>";

	print "</form>";

	print "</div>";

	}
}

?>
