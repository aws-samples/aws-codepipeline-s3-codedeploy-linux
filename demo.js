// 
// Here is how to define your module 
// has dependent on mobile-angular-ui
// 
var app = angular.module('PsaUsa', [
  'ngRoute',
  'mobile-angular-ui',
  
  // touch/drag feature: this is from 'mobile-angular-ui.gestures.js'
  // it is at a very beginning stage, so please be careful if you like to use
  // in production. This is intended to provide a flexible, integrated and and 
  // easy to use alternative to other 3rd party libs like hammer.js, with the
  // final pourpose to integrate gestures into default ui interactions like 
  // opening sidebars, turning switches on/off ..
  'mobile-angular-ui.gestures',
  'ngCookies',
  'ngSanitize'
]);

app.run(function($transform) {
  window.$transform = $transform;
});

// 
// You can configure ngRoute as always, but to take advantage of SharedState location
// feature (i.e. close sidebar on backbutton) you should setup 'reloadOnSearch: false' 
// in order to avoid unwanted routing.
// 
app.config(function($routeProvider) {
  $routeProvider.when('/',              {templateUrl: 'home.html', state: 'category', reloadOnSearch: false, cache: false, disableCache: true});
  $routeProvider.when('/search/:keywords?',{templateUrl: 'home.html', state: 'search', reloadOnSearch: false, cache: false, disableCache: true}); 
  $routeProvider.when('/login',        {templateUrl: 'login.html', state: 'login', reloadOnSearch: false}); 
  $routeProvider.when('/signup',        {templateUrl: 'login.html', state: 'signup', reloadOnSearch: false}); 
  $routeProvider.when('/tabs',          {templateUrl: 'tabs.html', reloadOnSearch: false}); 
  $routeProvider.when('/accordion',     {templateUrl: 'accordion.html', reloadOnSearch: false}); 
  $routeProvider.when('/overlay',       {templateUrl: 'overlay.html', reloadOnSearch: false}); 
  $routeProvider.when('/forms',         {templateUrl: 'forms.html', reloadOnSearch: false});
  $routeProvider.when('/dropdown',      {templateUrl: 'dropdown.html', reloadOnSearch: false});
  $routeProvider.when('/touch',         {templateUrl: 'touch.html', reloadOnSearch: false});
  $routeProvider.when('/swipe',         {templateUrl: 'swipe.html', reloadOnSearch: false});
  $routeProvider.when('/drag',          {templateUrl: 'drag.html', reloadOnSearch: false});
  $routeProvider.when('/drag2',         {templateUrl: 'drag2.html', reloadOnSearch: false});
  $routeProvider.when('/carousel',      {templateUrl: 'carousel.html', reloadOnSearch: false});
  $routeProvider.when('/category/:main?/:sub1?/:sub2?/:sub3?',{templateUrl: 'home.html', state: 'category',reloadOnSearch: false, cache: false, disableCache: true});
  $routeProvider.when('/product/:sku',{templateUrl: 'product.html', reloadOnSearch: false});
  $routeProvider.when('/validate/:validate',{templateUrl: 'validate.html', reloadOnSearch: false});
  $routeProvider.when('/checkout',{templateUrl: 'checkout.html', reloadOnSearch: false});
  $routeProvider.when('/thanks/:order',{templateUrl: 'thanks.html', reloadOnSearch: false});
  $routeProvider.when('/account',{templateUrl: 'account.html', reloadOnSearch: false,
	  resolve: {
		access: function (Products, $location) { if ( !Products.loggedIn() ) $location.path('/'); }
	  }
  });
});

// 
// `$touch example`
// 

app.directive('toucharea', ['$touch', function($touch){
  // Runs during compile
  return {
    restrict: 'C',
    link: function($scope, elem) {
      $scope.touch = null;
      $touch.bind(elem, {
        start: function(touch) {
          $scope.touch = touch;
          $scope.$apply();
        },

        cancel: function(touch) {
          $scope.touch = touch;  
          $scope.$apply();
        },

        move: function(touch) {
          $scope.touch = touch;
          $scope.$apply();
        },

        end: function(touch) {
          $scope.touch = touch;
          $scope.$apply();
        }
      });
    }
  };
}]);

//
// `$drag` example: drag to dismiss
//
app.directive('dragToDismiss', function($drag, $parse, $timeout){
  return {
    restrict: 'A',
    compile: function(elem, attrs) {
      var dismissFn = $parse(attrs.dragToDismiss);
      return function(scope, elem){
        var dismiss = false;

        $drag.bind(elem, {
          transform: $drag.TRANSLATE_RIGHT,
          move: function(drag) {
            if( drag.distanceX >= drag.rect.width / 4) {
              dismiss = true;
              elem.addClass('dismiss');
            } else {
              dismiss = false;
              elem.removeClass('dismiss');
            }
          },
          cancel: function(){
            elem.removeClass('dismiss');
          },
          end: function(drag) {
            if (dismiss) {
              elem.addClass('dismitted');
              $timeout(function() { 
                scope.$apply(function() {
                  dismissFn(scope);  
                });
              }, 300);
            } else {
              drag.reset();
            }
          }
        });
      };
    }
  };
});

//
// Another `$drag` usage example: this is how you could create 
// a touch enabled "deck of cards" carousel. See `carousel.html` for markup.
//
app.directive('carousel', function(){
  return {
    restrict: 'C',
    scope: {},
    controller: function() {
      this.itemCount = 0;
      this.activeItem = null;

      this.addItem = function(){
        var newId = this.itemCount++;
        this.activeItem = this.itemCount === 1 ? newId : this.activeItem;
        return newId;
      };

      this.next = function(){
        this.activeItem = this.activeItem || 0;
        this.activeItem = this.activeItem === this.itemCount - 1 ? 0 : this.activeItem + 1;
      };

      this.prev = function(){
        this.activeItem = this.activeItem || 0;
        this.activeItem = this.activeItem === 0 ? this.itemCount - 1 : this.activeItem - 1;
      };
    }
  };
});

app.directive('carouselItem', function($drag) {
  return {
    restrict: 'C',
    require: '^carousel',
    scope: {},
    transclude: true,
    template: '<div class="item"><div ng-transclude></div></div>',
    link: function(scope, elem, attrs, carousel) {
      scope.carousel = carousel;
      var id = carousel.addItem();
      
      var zIndex = function(){
        var res = 0;
        if (id === carousel.activeItem){
          res = 2000;
        } else if (carousel.activeItem < id) {
          res = 2000 - (id - carousel.activeItem);
        } else {
          res = 2000 - (carousel.itemCount - 1 - carousel.activeItem + id);
        }
        return res;
      };

      scope.$watch(function(){
        return carousel.activeItem;
      }, function(){
        elem[0].style.zIndex = zIndex();
      });
      
      $drag.bind(elem, {
        //
        // This is an example of custom transform function
        //
        transform: function(element, transform, touch) {
          // 
          // use translate both as basis for the new transform:
          // 
          var t = $drag.TRANSLATE_BOTH(element, transform, touch);
          
          //
          // Add rotation:
          //
          var Dx    = touch.distanceX, 
              t0    = touch.startTransform, 
              sign  = Dx < 0 ? -1 : 1,
              angle = sign * Math.min( ( Math.abs(Dx) / 700 ) * 30 , 30 );
          
          t.rotateZ = angle + (Math.round(t0.rotateZ));
          
          return t;
        },
        move: function(drag){
          if(Math.abs(drag.distanceX) >= drag.rect.width / 4) {
            elem.addClass('dismiss');  
          } else {
            elem.removeClass('dismiss');  
          }
        },
        cancel: function(){
          elem.removeClass('dismiss');
        },
        end: function(drag) {
          elem.removeClass('dismiss');
          if(Math.abs(drag.distanceX) >= drag.rect.width / 4) {
            scope.$apply(function() {
              carousel.next();
            });
          }
          drag.reset();
        }
      });
    }
  };
});

app.directive('dragMe', ['$drag', function($drag){
  return {
    controller: function($scope, $element) {
      $drag.bind($element, 
        {
          //
          // Here you can see how to limit movement 
          // to an element
          //
          transform: $drag.TRANSLATE_INSIDE($element.parent()),
          end: function(drag) {
            // go back to initial position
            drag.reset();
          }
        },
        { // release touch when movement is outside bounduaries
          sensitiveArea: $element.parent()
        }
      );
    }
  };
}]);

app.directive('checkImage', function() {
   return {
      link: function(scope, element, attrs) {
         element.bind('error', function() {
            element.attr('src', 'img/unknown.png'); // set default image
         });
       }
   }
});

app.factory('Products', function($rootScope, $q, $http, $interval, $cookieStore) {
	var products = [];
	var checking = null;
	var leadCheckInterval = 45;
	var session;
	
	function initSession() {
		var defer = $q.defer();
		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=session&callback=JSON_CALLBACK')
		.success(function (result) {
			if ( !result.error  ) {
				defer.resolve(result.data);
			}
			else defer.reject();
		})
		.error(function(data, status, headers, config) {
			defer.reject();
		});
		return defer.promise;
	}
	
	function validateLogin(session) {
		var defer = $q.defer();
		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=login&token='+encodeURIComponent(session)+'&callback=JSON_CALLBACK')
		.success(function (result) {
			if ( !result.error  ) {
				defer.resolve(result.data);
			}
			else defer.reject();
		})
		.error(function(data, status, headers, config) {
			defer.reject();
		});
		return defer.promise;
	}

	session = $cookieStore.get('psa_session');
	if ( session ) {
		validateLogin(session).then(
			function(value) { session = value; $rootScope.loggedIn = true; },
			function(error) { $rootScope.loggedIn = false; }
		);
	}
	if ( !session ) {
		initSession().then(
			function(value) {
				session = value;
				$cookieStore.put('psa_session', session);
			},
			function(error) { alert('error getting session value.'); }
		);
	}

	return {
		all: function() {
			return products;
		},
		startChecking: function() {
			var self = this;
			checking = $interval(self.load, leadCheckInterval*1000);
		},
		stopChecking: function() {
			$interval.cancel(checking);
		},
		session: function() {
			return session;
		},
		loggedIn: function() {
			return $rootScope.loggedIn;
		},
		validate: function(validate) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=validate&validate='+encodeURIComponent(validate)+'&callback=JSON_CALLBACK')
    		.success(function (result) {
    			if ( !result.error ) {
					defer.resolve(result.data);
        		}
        		else defer.reject('Unable to validate value.');
    		})
    		.error(function(data, status, headers, config) {
        		defer.reject('Unable to validate value.');
  			});
    		return defer.promise;
		},
		login: function(login) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=login&user='+encodeURIComponent(login.user)+'&pass='+encodeURIComponent(login.pass)+'&callback=JSON_CALLBACK')
    		.success(function (result) {
    			if ( !result.error ) {
					session = result.data;
					$cookieStore.put('psa_session', session);
					$rootScope.loggedIn = true;
					defer.resolve(session);
        		}
        		else defer.reject('Unable to log in.');
    		})
    		.error(function(data, status, headers, config) {
        		defer.reject('Unable to log in.');
  			});
    		return defer.promise;
		},
		logout: function() {
			session = null;
			initSession().then(
				function(value) {
					session = value;
					$cookieStore.put('psa_session', session);
				},
				function(error) { alert('error getting session value'); }
			);
			$rootScope.loggedIn = false;
		},
		signup: function(signup) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=signup&token='+encodeURIComponent(session)+'&email='+encodeURIComponent(signup.email)+'&user='+encodeURIComponent(signup.user)+'&pass='+encodeURIComponent(signup.pass)+'&callback=JSON_CALLBACK')
    		.success(function (result) {
    			console.log(result);
    			if ( !result.error ) {
					defer.resolve(true);
        		}
        		else defer.reject(result.data);
    		})
    		.error(function(data, status, headers, config) {
        		defer.reject('Unable to create user.');
  			});
    		return defer.promise;
		},
		load: function() {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=find&find='+encodeURIComponent('all')+'&callback=JSON_CALLBACK')
    		.success(function (data) {
    			if ( angular.isDefined(data.products) && angular.isArray(data.products) ) {
					products = data.products;
					defer.resolve();
        		}
    		})
    		.error(function(data, status, headers, config) {
        		defer.reject('Unable to get products');
  			});
    		return defer.promise;
		},
		getCategories: function(options) {
			var defer = $q.defer();
			if (!angular.isDefined(options.start)) options.start = 0;
			if (!angular.isDefined(options.number)) options.number = 100;
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=getCategories&path='+encodeURIComponent(options.path)+'&start='+encodeURIComponent(options.start)+'&number='+encodeURIComponent(options.number)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data, status, headers, config) {
        		defer.reject('Unable to get categories');
  			});
    		return defer.promise;
		},
		findProducts: function(options) {
			var defer = $q.defer();
			if (!angular.isDefined(options.start)) options.start = 0;
			if (!angular.isDefined(options.number)) options.number = 100;
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=findProducts&keywords='+encodeURIComponent(options.keywords)+'&start='+encodeURIComponent(options.start)+'&number='+encodeURIComponent(options.number)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data, status, headers, config) {
        		defer.reject('Unable to get categories');
  			});
    		return defer.promise;
		},
		getProduct: function(sku) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=getProduct&sku='+encodeURIComponent(sku)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) {
    				defer.resolve(results.data);
    			}
    			else defer.reject(results.error);
    		})
    		.error(function(data, status, headers, config) {
        		defer.reject('Unable to get product');
  			});
    		return defer.promise;
		},
		addCart: function(sku, quantity) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=addCart&token='+encodeURIComponent(session)+'&sku='+encodeURIComponent(sku)+'&quantity='+encodeURIComponent(quantity)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data,status) {
    			defer.reject(status);
    		});
			return defer.promise;
		},
		deleteCart: function(id) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=deleteCart&token='+encodeURIComponent(session)+'&id='+encodeURIComponent(id)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(data.error);
    		})
    		.error(function(data,status) {
    			defer.reject(status);
    		});
			return defer.promise;
		},
		getCart: function() {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=getCart&token='+encodeURIComponent(session)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data,status) {
    			defer.reject(status);
    		});
			return defer.promise;
		},
		getShipping: function(zip) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=getShipping&zip='+encodeURIComponent(zip)+'&token='+encodeURIComponent(session)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data,status) {
    			defer.reject(status);
    		});
			return defer.promise;
		},
		placeOrder: function(order) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=placeOrder&token='+encodeURIComponent(session)+'&json='+encodeURIComponent(JSON.stringify(order))+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data,status) {
    			defer.reject(status);
    		});
			return defer.promise;
		},
		accountAddress: function(address) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=accountAddress&token='+encodeURIComponent(session)+'&json='+encodeURIComponent(JSON.stringify(address))+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data,status) {
    			defer.reject(status);
    		});
			return defer.promise;
		},
		accountOrderHistory: function() {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=accountOrderHistory&token='+encodeURIComponent(session)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data,status) {
    			defer.reject(status);
    		});
			return defer.promise;
		},
		accountGetOrder: function(orderNum) {
			var defer = $q.defer();
    		$http.jsonp('http://www.oakhillsoftware.com/psausa/demo/api.php?action=accountGetOrder&orderNum='+encodeURIComponent(orderNum)+'&token='+encodeURIComponent(session)+'&callback=JSON_CALLBACK')
    		.success(function(results) {
    			if (!results.error) defer.resolve(results.data);
    			else defer.reject(results.error);
    		})
    		.error(function(data,status) {
    			defer.reject(status);
    		});
			return defer.promise;
		}
	}
});

app.controller('AccountController', function($scope, SharedState, Products) {	
	$scope.address = {};
	$scope.accountAddress = function() {
		Products.accountAddress($scope.address).then(function(data) {
			$scope.address = data;
		});
	}
	
	$scope.orders = [];
	$scope.accountOrderHistory = function() {
		Products.accountOrderHistory().then(function(data) {
			$scope.orders = data;
		});
	}
	
	SharedState.initialize($scope, 'orderNum');
	$scope.viewOrder = function(orderNum) {
		SharedState.set({'orderNum': orderNum});
		SharedState.turnOn('orderModal');
	}

	$scope.accountAddress();
	$scope.accountOrderHistory();
});

app.controller('OrderController', function($scope, SharedState, Products) {
	$scope.order = [];
	
	$scope.accountGetOrder = function(orderNum) {
		Products.accountGetOrder(orderNum).then(function(data) {
			console.log(data);
			$scope.order = data;
		});
	}
	
	$scope.accountGetOrder(SharedState.get('orderNum'));
});

app.controller('ThanksController', function($scope, $routeParams) {
	$scope.orderNum = $routeParams.order;
});

app.controller('CheckoutController', function($scope, $rootScope, $location, $anchorScroll, SharedState, Products) {
	if ( $rootScope.cartItems.length == 0 ) {
		alert('Your shopping cart is empty');
	}
	$scope.checkout = {};
	
	$scope.copy = function() {
		if ( $scope.checkout.copy ) {
			$scope.checkout.bill_name = $scope.checkout.ship_name;
			$scope.checkout.bill_company = $scope.checkout.ship_company;
			$scope.checkout.bill_address = $scope.checkout.ship_address;
			$scope.checkout.bill_address2 = $scope.checkout.ship_address2;
			$scope.checkout.bill_city = $scope.checkout.ship_city;
			$scope.checkout.bill_state = $scope.checkout.ship_state;
			$scope.checkout.bill_zip = $scope.checkout.ship_zip;
			$scope.checkout.bill_phone = $scope.checkout.ship_phone;
		}
	}
	
	$scope.validate = function(tab) {
		var error = '';
		switch(tab) {
			case 1:
				if ( !angular.isDefined($scope.checkout.ship_phone) || $scope.checkout.ship_phone == '' ) error = 'Please enter a phone';
				if ( !angular.isDefined($scope.checkout.ship_email) || $scope.checkout.ship_email == '' ) error = 'Please enter an email';
				if ( !angular.isDefined($scope.checkout.ship_zip) || $scope.checkout.ship_zip == '' ) error = 'Please enter a zip';
				if ( !angular.isDefined($scope.checkout.ship_state) || $scope.checkout.ship_state == '' ) error = 'Please enter a state';
				if ( !angular.isDefined($scope.checkout.ship_city) || $scope.checkout.ship_city == '' ) error = 'Please enter a city';
				if ( !angular.isDefined($scope.checkout.ship_address) || $scope.checkout.ship_address == '' ) error = 'Please enter an address';
				if ( !angular.isDefined($scope.checkout.ship_name) || $scope.checkout.ship_name == '' ) error = 'Please enter a name';
				break;
			case 2:
				if ( !angular.isDefined($scope.checkout.bill_phone) || $scope.checkout.bill_phone == '' ) error = 'Please enter a phone number';
				if ( !angular.isDefined($scope.checkout.bill_zip) || $scope.checkout.bill_zip == '' ) error = 'Please enter a zip';
				if ( !angular.isDefined($scope.checkout.bill_state) || $scope.checkout.bill_state == '' ) error = 'Please enter a state';
				if ( !angular.isDefined($scope.checkout.bill_city) || $scope.checkout.bill_city == '' ) error = 'Please enter a city';
				if ( !angular.isDefined($scope.checkout.bill_address) || $scope.checkout.bill_address == '' ) error = 'Please enter an address';
				if ( !angular.isDefined($scope.checkout.bill_name) || $scope.checkout.bill_name == '' ) error = 'Please enter a name';
				break;
			case 3:
				if ( !angular.isDefined($scope.checkout.bill_cvv) || $scope.checkout.bill_cvv == '' ) error = 'Please enter a cvv value';
				if ( !angular.isDefined($scope.checkout.bill_expiration) || $scope.checkout.bill_expiration == '' ) error = 'Please enter an expiration';
				if ( !angular.isDefined($scope.checkout.bill_account) || $scope.checkout.bill_account == '' ) error = 'Please enter an account number';
				break;
		}
		return error;
	}
	
	$scope.checkoutProgress = function() {
		var curTab = SharedState.get('checkoutTab');
		var err = $scope.validate(curTab);
		if ( err != '' ) {
			alert(err);
			return false;
		}
		else {
			var nextTab = 2;
			switch(curTab){
				case 1: nextTab = 2;break;
				case 2: nextTab = 3;break;
			}
			SharedState.set({'checkoutTab':nextTab});
			var elem = document.getElementById('myScrollableContent');
			elem.scrollTop = 0;
			return true;
		}
	}
	
	$scope.placeOrder = function() {
		if ( $scope.checkoutProgress() ) {
			Products.placeOrder($scope.checkout).then(function(data) {
				var orderNum = data;
				alert('Order Number: '+orderNum);
				$location.path('/thanks/'+orderNum);
			},
			function(err) {
				alert(err);
			});
		}
	}
});

app.controller('CartController', function($scope, $rootScope, Products) {
  $rootScope.cartItems = [];
  
  $scope.getCart = function() {
  	Products.getCart().then(
  		function(data) { $rootScope.cartItems = data; },
  		function(error) {  }
  	);
  }
  
  $scope.estimate = {'zip':'01234'};
  $scope.estimateShipping = function(zip) {
  	Products.getShipping(zip).then(
  		function(data) {
  			var msg = "Estimated shipping to zip code "+data.destZip+" is $"+data.shipCost+". ";
  			if ( data.allShip == 0 ) msg += "Note some items in the cart were not included in the shipping cost estimate.";
  			alert(msg);
  		},
  		function(error) { alert("There was an error estimating shipping."); }
  	);
  }
  
  $scope.deleteCart = function(id) {
  	Products.deleteCart(id).then(
		function(data) { $rootScope.cartItems = data; },
		function(error) {}
	);
  }
  
  $scope.quick = {'sku':'','quantity':'1'};
  $scope.quickAdd = function(quick) {
  	Products.addCart(quick.sku, quick.quantity).then(
  		function(data) { $rootScope.cartItems = data; },
  		function(error) {}
  	);
  }
  
  $scope.getCart();
});

app.controller('ProductController', function($scope, $rootScope, $routeParams, SharedState, Products) {
	$scope.product = {quantity:1};
	$scope.getProduct = function(sku) {
		var promise = Products.getProduct(sku).then(
			function(data) {
				console.log(data);
				$scope.product = data;
				$scope.product.quantity = parseInt($scope.product.minQty);
			},
			function(error) {
				alert(error);
			}
		);
	}
	
	$scope.addCart = function(sku) {
		//verify quantity is multiple of minQty or else increase to next level
		$scope.product.quantity = parseInt($scope.product.quantity);
		if ( !angular.isNumber($scope.product.quantity) || $scope.product.quantity <= 0 ) {
			alert('Please check your quantity and try again');
			return false;
		}
		if ( ($scope.product.quantity % $scope.product.minQty) > 0 ) {
			alert('You must add multiples of '+$scope.product.minQty+' to the cart. The quantity has been updated to the next level.');
			$scope.product.quantity = $scope.product.quantity + ($scope.product.minQty-($scope.product.quantity%$scope.product.minQty));
		}
		else {
			var promise = Products.addCart(sku, $scope.product.quantity);
			promise.then(function(data) {
				$rootScope.cartItems = data;
				alert('added to cart');
				SharedState.turnOff('productModal');
				SharedState.set({'product':''});
			}, function(error) {
				alert(error);
			});
		}
	}

	if ( angular.isDefined($routeParams.sku) ) $scope.getProduct($routeParams.sku);
	else $scope.getProduct(SharedState.get('product'));
});

app.controller('HomeController', function($scope, $rootScope, $routeParams, $route, $location, SharedState, Products) {
  $scope.categories = [];
  $scope.catOptions = {main:"",sub1:"",sub2:"",sub3:"",path:"/",start:0,number:50,total:-1,keywords:""};
  $scope.products = [];
  if ( angular.isDefined($routeParams.main) ) {$scope.catOptions.main = $routeParams.main; $scope.catOptions.path += $routeParams.main;}
  if ( angular.isDefined($routeParams.sub1) ) {$scope.catOptions.sub1 = $routeParams.sub1; $scope.catOptions.path += "/"+$routeParams.sub1;}
  if ( angular.isDefined($routeParams.sub2) ) {$scope.catOptions.sub2 = $routeParams.sub2; $scope.catOptions.path += "/"+$routeParams.sub2;}
  if ( angular.isDefined($routeParams.sub3) ) {$scope.catOptions.sub3 = $routeParams.sub3; $scope.catOptions.path += "/"+$routeParams.sub2;}
  if ( angular.isDefined($routeParams.keywords) ) {$scope.catOptions.keywords = $routeParams.keywords;}
  $scope.showLoading = 0;

  $scope.pageProducts = function(page) {
  	if ( $scope.catOptions.keywords != '' ) $scope.findProducts(page);
  	else $scope.getProducts(page);
  }
    
  $scope.getProducts = function(page) {
	  if ( page == 1 ) $scope.catOptions.start+=$scope.catOptions.number;
	  else if ( page == -1 ) $scope.catOptions.start-=$scope.catOptions.number;
	  else $scope.catOptions.start=0;
	  if ( $scope.catOptions.start < 0 ) $scope.catOptions.start = 0;
	  if ( $scope.catOptions.total >= 0 && $scope.catOptions.start >= $scope.catOptions.total ) $scope.catOptions.start = 0;
      //$scope.showLoading = 1;
      $rootScope.loading = true;
      var promise = Products.getCategories($scope.catOptions);
	  promise.then(
		function(data) { //success
			if ( angular.isDefined(data.categories) ) $scope.categories = data.categories;
			if ( angular.isDefined(data.products) ) {
				$scope.products = data.products;
				$scope.catOptions.total = data.total;
			}
		}, 
		function(reason) { //error
			alert(reason);
	  }).finally(function() { 
	  //$scope.showLoading = 0;
	  $rootScope.loading = false;
	  });
  }

  $scope.findProducts = function(page) {
	  if ( page == 1 ) $scope.catOptions.start+=$scope.catOptions.number;
	  else if ( page == -1 ) $scope.catOptions.start-=$scope.catOptions.number;
	  else $scope.catOptions.start=0;
	  if ( $scope.catOptions.start < 0 ) $scope.catOptions.start = 0;
	  if ( $scope.catOptions.total >= 0 && $scope.catOptions.start >= $scope.catOptions.total ) $scope.catOptions.start = 0;
	  //$scope.showLoading = 1;
	  $rootScope.loading = true;
	  var promise = Products.findProducts($scope.catOptions);
	  promise.then(
		function(data) { //success
			$scope.categories = [];
			if ( angular.isDefined(data.products) ) {
				$scope.products = data.products;
				$scope.catOptions.total = data.total;
			}
		}, 
		function(reason) { //error
			alert(reason);
	  }).finally(function() { 
	  //$scope.showLoading = 0;
	  $rootScope.loading = false; 
	  });
  }
  
  $scope.addCart = function(sku,quantity) {
  	var promise = Products.addCart(sku, quantity);
  	promise.then(function(data) {
  		$rootScope.cartItems = data;
  		alert('added to cart');
  	}, function(error) {
  		alert(error);
  		console.log(error);
  	});
  }  

  // do the proper search
  if ($route.current.$$route.state == 'search' ) $scope.findProducts(0);
  else $scope.getProducts(0);

});

app.filter('pathEncode', function() {
    return function(input) {
        return input.replace("/","%2F");
    };
});

app.controller('ValidationController', function($scope, $location, $route, $routeParams, Products) {
  $scope.validate = function(value) {
  	Products.validate(value).then(function(data) {
  		if ( data == 1 ) alert('Validation successful. Please log in.');
  		else alert('Validation already complete.');
  		$scope.goTo('/login');
  		console.log(data);
  	},
  	function(err) {
  		alert(err);
  	});
  }
  if ( angular.isDefined($routeParams.validate) ) {$scope.validate($routeParams.validate)}
});

app.controller('MainController', function($rootScope, $scope, $location, $route, $routeParams, SharedState, Products){  
  $scope.swiped = function(direction) {
    alert('Swiped ' + direction);
  };

  // User agent displayed in home page
  $scope.userAgent = navigator.userAgent;
  
  // Needed for the loading screen
  $rootScope.$on('$routeChangeStart', function(){
    $rootScope.loading = true;
  });

  $rootScope.$on('$routeChangeSuccess', function(){
    $rootScope.loading = false;
  });

  // Fake text i used here and there.
  $scope.lorem = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit. Vel explicabo, aliquid eaque soluta nihil eligendi adipisci error, illum corrupti nam fuga omnis quod quaerat mollitia expedita impedit dolores ipsam. Obcaecati.';

  $scope.bottomReached = function() {
    /* global alert: false; */
  };

  $scope.rootCategories = [];
  $scope.getRoot = function() {
      var promise = Products.getCategories({'path':'/'});
	  promise.then(
		function(data) { //success
			if ( angular.isDefined(data.categories) ) $scope.rootCategories = data.categories;
		}, 
		function(reason) { //error
			alert(reason);
	  }).finally(function() { 

	  });
  }
  $scope.getRoot();

  $scope.selectCategory = function(path, category) {
  	var fullpath = "/category"+path;
  	if ( path[path.length-1] != '/' ) fullpath += '/';
  	fullpath += category.name.replace("/","%2F");
  	$location.path(fullpath);
  }
  
  $scope.goBack = function() {
  	window.history.back();
  }
  
  $scope.goTo = function(path) {
  	$location.path(path);
  }
  
  $scope.login = {'user': 'me@example.com','pass':''};

  $scope.login = function() {
    Products.login($scope.login).then(function(data) {
    	$scope.login.user = '';
    	$scope.login.pass = '';
    	$location.path("/");
    },
    function(err) {
    	alert(err);
    });
  };

  $scope.logout = function() {
  	Products.logout();
  }
  
  $scope.signup = {'email':'','user':'','pass':''};
  
  $scope.signup = function() {
    Products.signup($scope.signup).then(function(data) {
    	if ( !data.error ) {
    		alert('Signup successful. Please check your email for a link that will validate your account.');
			$scope.signup.email = '';
			$scope.signup.user = '';
			$scope.signup.pass = '';
			$location.path("/login");
			SharedState.turnOn('loginForm');
    	}
    },
    function(err) {
    	alert(err);
    });
  }
  
  // 
  // 'Drag' screen
  // 
  $scope.notices = [];
  
  for (var j = 0; j < 10; j++) {
    $scope.notices.push({icon: 'envelope', message: 'Notice ' + (j + 1) });
  }

  $scope.deleteNotice = function(notice) {
    var index = $scope.notices.indexOf(notice);
    if (index > -1) {
      $scope.notices.splice(index, 1);
    }
  };
});