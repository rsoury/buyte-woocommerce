;(function($){
	var ShippingTable = $('#shipping-methods-table tbody');
	var ButtonClass = '.button-primary';
	var Row = ShippingTable.find('tr').clone();
	var CurrentValue = $('#woocommerce_buyte_shipping_methods').val();

	var Ops = {
		getLastRow: function(){
			var lastRow = ShippingTable.find('tr');
			lastRow = $(lastRow.get(lastRow.length - 1));
			return lastRow;
		},
		newRow: function(method){
			var row = Row.clone();
			if(method){
				row.find('.shipping-title-input').val(method.title);
				row.find('.shipping-price-input').val(method.price);
				row.find('.shipping-desc-input').val(method.desc);
			}
			row.find('.shipping-title-input, .shipping-price-input, .shipping-desc-input').blur(this.onSave);
			return row;
		},
		addRow: function(e){
			var newRow = this.newRow();
			newRow.find(ButtonClass).click(this.addRow);
			var lastRow = this.getLastRow();
			lastRow.find(ButtonClass).text('-').unbind().off().click(this.removeRow);
			ShippingTable.append(newRow);
		},
		removeRow: function(e){
			$(e.target).closest('tr').remove();
			var lastRow = this.getLastRow();
			lastRow.find(ButtonClass).text('+').unbind().off().click(this.addRow);
			this.onSave();
		},
		cleanAmount: function(amount){
			return (Math.round(parseFloat(amount) * 100) / 100).toFixed(2) + '';
		},
		validMethod: function(method){
			if(!method.title || !method.price){
				return false;
			}
			if(isNaN(parseFloat(method.price))){
				return false;
			}
			return method;
		},
		onSave: function(e){
			var shippingMethods = [];
			var _this = this;
			ShippingTable.find('tr').each(function(index, row){
				var $row = $(row);
				var method = {
					title: $row.find('.shipping-title-input').val() || '',
					price: $row.find('.shipping-price-input').val() || '0.00',
					desc: $row.find('.shipping-desc-input').val() || ''
				};
				if(_this.validMethod(method)){
					method.price = _this.cleanAmount(method.price);
					shippingMethods.push(method);
				}
			});
			console.log(shippingMethods);
			$('#woocommerce_buyte_shipping_methods').val(JSON.stringify(shippingMethods));
		},
		onLoad: function(){
			this.bindAll.bind(this)();
			ShippingTable.find(ButtonClass).click(this.addRow);
			ShippingTable.find('tr').find('.shipping-title-input, .shipping-price-input, .shipping-desc-input').blur(this.onSave);
			try{
				var prefill = CurrentValue ? JSON.parse(CurrentValue) : [];
				if(prefill ? prefill.length : false){
					for(var i = prefill.length - 1; i >= 0; i --){
						var method = prefill[i];
						var newRow = this.newRow(method);
						newRow.find(ButtonClass).text('-').unbind().off().click(this.removeRow);
						ShippingTable.prepend(newRow);
					}
				}
			}catch(e){}
		},
		bindAll: function(){
			this.getLastRow = this.getLastRow.bind(this);
			this.newRow = this.newRow.bind(this);
			this.addRow = this.addRow.bind(this);
			this.removeRow = this.removeRow.bind(this);
			this.cleanAmount = this.cleanAmount.bind(this);
			this.validMethod = this.validMethod.bind(this);
			this.onSave = this.onSave.bind(this);
		}
	};

	Ops.onLoad();
})(window.jQuery || window.$);