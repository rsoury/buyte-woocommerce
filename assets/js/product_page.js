(function($) {
	$(document.body)
		.on("show_variation", function(event, variation) {
			console.log(variation);

			window.Buyte("update", {
				items: [
					{
						name: attributes ? name + ", " + attributes : name,
						amount: parseInt(variation.display_price) * 100
					}
				]
			});

			window.buyte_success_endpoint =
				window.buyte_original_success_endpoint +
				"&variation_id=" +
				variation.variation_id;
		})
		.on("hide_variation", function(event) {});
})(window.jQuery || window.$);
