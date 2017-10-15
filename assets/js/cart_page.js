(function($){
	$( document.body ).on( 'updated_cart_totals', function(){
		var $script = $('script.buyte-mobile-payment');
    	$.ajax({
    		url: '/?p=buyte&route=payment&action_type=cart',
    		method: 'GET',
    		success: function(data){
    			try{
    				data = JSON.parse(data);
    				if(data ? typeof data === 'object' : false){
    					$script.attr(data);
						$script.each(function() {
							$.each(this.attributes, function() {
								if(this.specified) {
									if(this.name.indexOf('data-') === 0){
										if(!data.hasOwnProperty(this.name)){
											$script.removeAttr(this.name);
										}
									}
								}
							});
						});
    					window.Buyte('reload');
    				}
    			}catch(e){}
    		},
    		error: function(data){}
    	})
	});
})(window.jQuery || window.$);