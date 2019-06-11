(function($) {
	window.Buyte("onReady", function(settings) {
		// Enable on ready
		window.Buyte("enable");

		// Listen for quantity
		var $quantity = $('form [name="quantity"]');
		var quantity = $quantity.val();
		$quantity.on("change", function() {
			var $this = $(this);
			quantity = $this.val();
			quantity = parseInt(quantity);
			var updateSettings = {
				items: settings.items
			};
			updateSettings.items[0].quantity = quantity;
			console.log(updateSettings);
			window.Buyte("update", updateSettings);
		});

		// Add events if variation form
		var $form = $(".single_variation_wrap");
		$form
			.on("show_variation", function(event, variation) {
				if (!variation.is_purchasable) {
					window.Buyte("disable");
					return;
				}

				var variationExtensions = [];
				for (var key in variation.attributes) {
					variationExtensions.push(variation.attributes[key]);
				}

				window.Buyte("update", {
					items: [
						{
							name:
								variationExtensions.length > 0
									? settings.items[0].name +
									  " -- " +
									  variationExtensions.join(", ")
									: settings.items[0].name,
							amount: parseInt(
								Math.round(
									parseFloat(variation.display_price) * 100
								)
							),
							quantity: quantity
						}
					]
				});

				window.buyte_success_endpoint =
					window.buyte_original_success_endpoint +
					"&variation_id=" +
					variation.variation_id;

				window.Buyte("enable");
			})
			.on("hide_variation", function(event) {
				window.Buyte("disable");
			});
	});
})(window.jQuery || window.$);
