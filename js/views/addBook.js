

var app = app || {};

app.AddBookView = Backbone.View.extend({
    el: '#addBook',
    template: _.template( $( '#addBookTemplate' ).html() ),
    
    events:{
     	 "submit #addBookForm" : "addBook"     		 
    },

    initialize: function( ) {
        this.render();
    },

    render: function() {
        //this.el is what we defined in tagName. use $el to get access to jQuery html() function
        this.$el.append( this.template());
        return this;
    },
    
    addBook: function(){
    	alert("Form submitted");
    }
    
});
