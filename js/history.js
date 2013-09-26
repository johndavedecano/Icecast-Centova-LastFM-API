function HistoryController($scope,$http){
	
	$scope.loadJson = function(){
		$http({method: 'POST', url: './history.php',timeout:5000}).success(function(data,status){
			if(status == 200){
				$scope.items = data;
				console.log('History Updated');	
			}
		});
	}
    
     /*$scope.refreshHistory = function(){
  		$http({method: 'GET', url: './cron.php',timeout:5000}).success(function(data,status){
 			if(status == 200){
  				console.log('History has been refreshed.');	
  			}
  		});
     }*/

	$scope.loadJson();
    
	
}
                           
window.setInterval(function(){
    var app = document.getElementById('recent-tracks');
    var scope = angular.element(app).scope();
    scope.$apply(function() {
        scope.loadJson();
    });   
},5000);
/*
window.setInterval(function(){
    var app = document.getElementById('recent-tracks');
    var scope = angular.element(app).scope();
    scope.$apply(function() {
        scope.refreshHistory();
    }); 
},30000);*/