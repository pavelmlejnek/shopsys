(function ($){
	$(document).ready(function () {
		var $productEditForm = $('form[name="product_edit"]');
		$productEditForm.jsFormValidator({
			'groups': function () {

				var groups = [SS6.constant('\\SS6\\ShopBundle\\Form\\ValidationGroup::VALIDATION_GROUP_DEFAULT')];

				if ($('input[name="product_edit[productData][priceCalculationType]"]:checked').val() === SS6.constant('\\SS6\\ShopBundle\\Model\\Product\\Product::PRICE_CALCULATION_TYPE_MANUAL')) {
					groups.push(SS6.constant('\\SS6\\ShopBundle\\Form\\Admin\\Product\\ProductEditFormType::VALIDATION_GROUP_MANUAL_PRICE_CALCULATION'));
				}

				return groups;
			}
		});
		var $productForm = $('#product_edit_productData');
		$productForm.jsFormValidator({
			'groups': function () {

				var groups = [SS6.constant('\\SS6\\ShopBundle\\Form\\ValidationGroup::VALIDATION_GROUP_DEFAULT')];

				if ($('input[name="product_edit[productData][usingStock]"]:checked').val() === '1') {
					groups.push(SS6.constant('\\SS6\\ShopBundle\\Form\\Admin\\Product\\ProductFormType::VALIDATION_GROUP_USING_STOCK'));
				} else {
					groups.push(SS6.constant('\\SS6\\ShopBundle\\Form\\Admin\\Product\\ProductFormType::VALIDATION_GROUP_NOT_USING_STOCK'));
				}

				if ($('input[name="product_edit[productData][priceCalculationType]"]:checked').val() === SS6.constant('\\SS6\\ShopBundle\\Model\\Product\\Product::PRICE_CALCULATION_TYPE_AUTO')) {
					groups.push(SS6.constant('\\SS6\\ShopBundle\\Form\\Admin\\Product\\ProductFormType::VALIDATION_GROUP_AUTO_PRICE_CALCULATION'));
				}

				return groups;
			}
		});
	});
})(jQuery);