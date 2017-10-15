(function($){
	var BuyteEnabled = true;
	var toggleBuyte = function(enable){
		var $buyte = $('#buyte-container');
		if($buyte.length){
			$buyte.css(enable ? {
				'pointer-events': 'auto',
				'opacity': '1'
			} : {
				'pointer-events': 'none',
				'opacity': '0.5'
			});
		}	
	};
	window.Buyte('onLoad', function(){
		toggleBuyte(BuyteEnabled);
	});
	$( ".single_variation_wrap" )
		.on( "show_variation", function( event, variation ){
		    var $script = $('script.buyte-mobile-payment');
		    var name = $script.attr('data-item-wc-product-name');
		    var attributes = '';
		    for(var key in variation.attributes){
		    	attributes += variation.attributes[key] + ', ';
		    }
		    if(attributes.length > 2){
		    	attributes = attributes.slice(0, -2);
		    }

		    $('script.buyte-mobile-payment').attr({
		    	'data-item-label': attributes ? name + ', ' + attributes : name,
		    	'data-item-amount': variation.display_price.toFixed(2)
		    });

		    window.Buyte('reload');
		    
		    BuyteEnabled = true;
			toggleBuyte(BuyteEnabled);
		})
		.on( "hide_variation", function( event ){
			BuyteEnabled = false;
			toggleBuyte(BuyteEnabled);
		});
})(window.jQuery || window.$);