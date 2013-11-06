(function ($) {

	"use strict";

	var app = angular.module('RbsChange');


	/**
	 * Routes and URL definitions.
	 */
	app.config(['$provide', function ($provide)
	{
		$provide.decorator('RbsChange.UrlManager', ['$delegate', function ($delegate)
		{
			$delegate.model('Rbs_Tag')
				.route('home', 'Rbs/Tag', { 'redirectTo': 'Rbs/Tag/Tag/'})
				.route('myTags', 'Rbs/Tag/MyTags/', { 'templateUrl': 'Rbs/Tag/Tag/myTags-list.twig'});
			$delegate.routesForModels(['Rbs_Tag_Tag']);
			return $delegate;
		}]);
	}]);


	// Register default editors:
	// Do not declare an editor here if you have an 'editor.js' for your Model.
	__change.createEditorForModel('Rbs_Tag_Tag');


	//-------------------------------------------------------------------------
	//
	// Configuration and handlers.
	//
	//-------------------------------------------------------------------------


	/**
	 * Attach handlers:
	 * - create new tags in a Document's Editor (pre-save).
	 * - affect tags in a Document's Editor (post-save).
	 * - build predicates for "filter:hasTag" in <rbs-document-list/>.
	 */
	app.run(['RbsChange.TagService', 'RbsChange.Loading', 'RbsChange.Events', 'RbsChange.i18n', '$rootScope', '$q', '$timeout', function (TagService, Loading, Events, i18n, $rootScope, $q, $timeout) {

		// Create new and unsaved tags when an Editor is submitted.
		$rootScope.$on(Events.EditorPreSave, function (event, args) {
			angular.forEach(args.document.META$.tags, function (tag) {
				if (tag.unsaved) {
					args.promises.push(TagService.create(tag));
				}
			});
		});

		// Affect tags to a document that has just been saved in an Editor.
		$rootScope.$on(Events.EditorPostSave, function (event, args) {
			var	doc = args.document,
				tags = doc.META$.tags;
			if (angular.isArray(tags) && tags.length) {
				args.promises.push(TagService.setDocumentTags(doc, tags));
			}
		});

		// Load tags of the document being edited in an Editor.
		$rootScope.$on(Events.EditorLoaded, function (event, args) {
			var doc = args.document;
			if (doc.model !== 'Rbs_Tag_Tag') {
				args.promises.push(doc.loadTags());
			}
		});


		// Filter 'hasTag' for <rbs-document-list/>.
		$rootScope.$on(Events.DocumentListApplyFilter, function (event, args) {
			var	filter = args.filter,
				predicates = args.predicates,
				p, filterName, filterValue, tags;

			p = filter.indexOf(':');
			if (p === -1) {
				return;
			}

			filterName = filter.substring(0, p);
			if (filterName !== 'hasTag') {
				return;
			}

			filterValue = filter.substring(p+1);
			tags = filterValue.split(/,/);
			for (p=0 ; p<tags.length ; p++) {
				predicates.push({
					"op"  : "hasTag",
					"tag" : parseInt(tags[p], 10)
				});
			}
		});

		$rootScope.$on(Events.EditorFormButtonBarContents, function (event, args) {
			if (args.document.model !== 'Rbs_Tag_Tag') {
				args.contents.push('<div>' + i18n.trans('m.rbs.tag.admin.js.tags | ucf')  + '<rbs-tag-selector ng-model="document.META$.tags"></rbs-tag-selector></div>');
			}
		});


		function affectTagToDocuments (tag, documents) {
			var promises = [];
			angular.forEach(documents, function (doc) {
				promises.push(TagService.addTagToDocument(tag, doc));
			});
			return $q.all(promises);
		}


		// Attach 'drop' handler on every <rbs-document-list/> to allow setting a Tag to the selected documents
		// with drag and drop.
		$(document).on({

			'dragover.rbs.tag' : function (e) {
				e.dataTransfer.dropEffect = "copy";
				e.preventDefault();
				e.stopPropagation();
			},

			'drop.rbs.tag' : function (e) {
				e.dataTransfer.dropEffect = "copy";
				e.preventDefault();
				e.stopPropagation();
				var	tag = e.dataTransfer.getData('Rbs/Tag'),
					documents = $(this).scope().selectedDocuments;
				if (tag && documents && documents.length) {
					$timeout(function () {
						Loading.start(i18n.trans('m.rbs.tag.admin.js.applying-tags | ucf | etc'));
						affectTagToDocuments(JSON.parse(tag), documents).then(
							function () {
								Loading.stop();
							},
							function () {
								// TODO Notify user.
								Loading.stop();
							}
						);
					});
				}
			}

		}, 'rbs-document-list');

	}]);


	//-------------------------------------------------------------------------
	//
	// TagService
	//
	//-------------------------------------------------------------------------


	app.service('RbsChange.TagService', ['$rootScope', 'RbsChange.REST', 'RbsChange.Utils', '$q', '$http', function ($rootScope, REST, Utils, $q, $http) {

		function getIdArray (data) {
			var ids = [];
			angular.forEach(data, function (item) {
				ids.push(item.id);
			});
			return ids;
		}

		return {

			'getList' : function (defered) {
				var tags = [];
				var tagQuery = {
					"model": "Rbs_Tag_Tag",
					"where": {
						"and": [
							{
								"or" : [
									{
										"op" : "eq",
										"lexp" : {
											"property" : "authorId"
										},
										"rexp" : {
											"value" : $rootScope.user.id
										}
									},
									{
										"op" : "eq",
										"lexp" : {
											"property" : "userTag"
										},
										"rexp" : {
											"value" : false
										}
									}
								]
							},
							{
								"or" : [
									{
										"op" : "eq",
										"lexp" : {
											"property" : "module"
										},
										"rexp" : {
											"value" : $rootScope.rbsCurrentPluginName
										}
									},
									{
										"op" : "isNull",
										"exp" : {
											"property" : "module"
										}
									}
								]
							}
						]
					},
					"order": [
						{
							"property": "userTag",
							"order": "desc"
						},
						{
							"property": "label",
							"order": "asc"
						}
					],
					"offset": 0
				};

				REST.query(tagQuery, {'column': ['color','userTag'], 'limit': 1000}).then(function (result) {
					angular.forEach(result.resources, function (r) {
						tags.push(r);
					});
					if (defered) {
						defered.resolve(tags);
					}
				});
				return tags;
			},

			'create' : function (tag) {
				tag.model = 'Rbs_Tag_Tag';
				tag.id = Utils.getTemporaryId();
				var promise = REST.save(tag);
				promise.then(function (created) {
					angular.extend(tag, created);
					delete tag.unsaved;
				});
				return promise;
			},

			'setDocumentTags' : function (doc, tags) {
				var q = $q.defer();
				$http.post(doc.getTagsUrl(), {"ids": getIdArray(tags)}, REST.getHttpConfig())
					.success(function (data) {
						q.resolve(doc);
					})
					.error(function errorCallback (data, status) {
						data.httpStatus = status;
						q.reject(data);
					});
				return q.promise;
			},

			'addTagToDocument' : function (tag, doc) {
				var q = $q.defer();
				$http.put(doc.getTagsUrl(), {"addIds": [tag.id]}, REST.getHttpConfig())
					.success(function (data) {
						if (!angular.isArray(doc.META$.tags)) {
							doc.META$.tags = [];
						}
						doc.META$.tags.push(tag);
						q.resolve(doc);
					})
					.error(function errorCallback (data, status) {
						data.httpStatus = status;
						q.reject(data);
					});
				return q.promise;
			}

		};

	}]);

	app.controller('Rbs_Tag_Tag_MyTagsController', ['$rootScope', '$scope', function ($rootScope, $scope) {
		$scope.myTagsQuery = {
			"model": "Rbs_Tag_Tag",
			"where": {
				"and" : [
					{
						"op" : "eq",
						"lexp" : {
							"property" : "authorId"
						},
						"rexp" : {
							"value": $rootScope.user.id
						}
					},
					{
						"op" : "eq",
						"lexp" : {
							"property" : "userTag"
						},
						"rexp" : {
							"value": true
						}
					}
				]
			}
		};
	}]);

	app.controller('Rbs_Tag_Tag_TagsController', ['$rootScope', '$scope', function ($rootScope, $scope) {
		$scope.tagsQuery = {
			"model": "Rbs_Tag_Tag",
			"where": {
				"and" : [
					{
						"op" : "eq",
						"lexp" : {
							"property" : "userTag"
						},
						"rexp" : {
							"value": false
						}
					}
				]
			}
		};
	}]);

})(window.jQuery);