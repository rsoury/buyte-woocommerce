(function($) {
	// Listen for cart changes
	$(document.body).on("updated_cart_totals", function() {
		console.log("Updated cart totals");
		// on cart change, request cart data from server.
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
