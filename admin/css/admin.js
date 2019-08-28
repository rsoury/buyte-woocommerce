(function($) {
	function onDeveloperToggled(show) {
		for (var i = 0; i < config.affected.length; i++) {
			var $row = $("#" + config.affected[i]).closest("tr");
			if (show) {
				$row.show();
			} else {
				$row.hide();
			}
		}
	}

	$(document).ready(function() {
		var $toggle = $("#" + config.toggle);
		onDeveloperToggled($toggle.is(":checked"));
		$toggle.on("change", function() {
			onDeveloperToggled($toggle.is(":checked"));
		});
		$("#mainform").show();
	});
})(window.jQuery);
