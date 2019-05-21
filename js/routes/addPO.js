var app = app || {};

var PORouter = Backbone.Router.extend({

	  routes: {
	    "addPO": "addPO"    // #addPO
	  },

	  addPO: function() {
	   
	  }


});

var poRouter = new PORouter();

Backbone.history.start();
