var app = app || {};

app.AddPOView = Backbone.View.extend({
    el: '#addPO',
    template: _.template( $( '#addPOTemplate' ).html() ),
    
    events:{
     	 "submit #addPOForm" : "addPO"     		 
   },

    initialize: function( ) {
        this.render();
    },

    render: function() {
        //this.el is what we defined in tagName. use $el to get access to jQuery html() function
        this.$el.append( this.template());
        return this;
    },
    
    addPO: function(){
    	//wherever you need to do the ajax
    	Backbone.ajax({
    	    dataType: "jsonp",
    	    url: "https://api.twitter.com/1/statuses/user_timeline.json?include_entities=true&include_rts=true&screen_name=twitterapi&count=25",
    	    data: "",
    	    success: function(val){
    	        collection.add(val);  //or reset
    	        console.log(collection);
    	    }
    	});
    }
    
});
