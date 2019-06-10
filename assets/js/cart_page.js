(function($) {
	$(document.body).on("updated_cart_totals", function() {
		$.ajax({
			url: "/?p=buyte&action_type=cart",
			method: "GET",
			success: function(data) {
				try {
					data = JSON.parse(data);
					if (data ? typeof data === "object" : false) {
						console.log(data);
					}
				} catch (e) {
					console.error(e);
				}
			},
			error: function(e) {
				console.error(e);
			}
		});
	});
})(window.jQuery || window.$);
