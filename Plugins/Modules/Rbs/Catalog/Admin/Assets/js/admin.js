(function () {
	"use strict";

	var app = angular.module('RbsChange');

	// Register default editors:
	// Do not declare an editor here if you have an 'editor.js' for your Model.
	__change.createEditorForModel('Rbs_Catalog_ProductList');
	__change.createEditorForModel('Rbs_Catalog_ProductListItem');

	__change.createEditorsForLocalizedModel('Rbs_Catalog_Attribute');

	__change.createEditorForModelTranslation('Rbs_Catalog_Product');

	/**
	 * Routes and URL definitions.
	 */
	app.config(['$provide', function ($provide)
	{
		$provide.decorator('RbsChange.UrlManager', ['$delegate', function ($delegate)
		{
			$delegate.model('Rbs_Catalog_Product')
				.route('prices', 'Rbs/Catalog/Product/:id/Prices/', 'Rbs/Catalog/Product/product-prices.twig');

			$delegate.model('Rbs_Catalog_ProductList')
				.route('productListItems', 'Rbs/Catalog/ProductList/:id/ProductListItem/', 'Rbs/Catalog/ProductList/products.twig');

			$delegate.model('Rbs_Catalog')
				.route('home', 'Rbs/Catalog', { 'redirectTo': 'Rbs/Catalog/Product/'});

			$delegate.routesForLocalizedModels(['Rbs_Catalog_Product', 'Rbs_Catalog_Attribute']);
			$delegate.routesForModels(['Rbs_Catalog_ProductList', 'Rbs_Catalog_VariantGroup']);
			return $delegate;
		}]);
	}]);

	app.controller('rbsProductCategorizableSelector', function ($scope) {
		$scope.productCategorizableQuery= { "model": "Rbs_Catalog_Product",
			"where": {
				"and" : [
					{
						"op" : "eq",
						"lexp" : {
							"property" : "categorizable"
						},
						"rexp" : {
							"value": true
						}
					}
				]
			}
		};
	});

	app.controller('rbsProductEmptyGroupSelector', function ($scope) {
		$scope.productEmptyGroupQuery= { "model": "Rbs_Catalog_Product",
			"where": {
				"and" : [
					{
						"op" : "eq",
						"lexp" : {
							"property" : "variantGroup"
						},
						"rexp" : {
							"value": 0
						}
					}
				]
			}
		};
	});
})();