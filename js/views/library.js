// js/views/library.js

var app = app || {};

app.LibraryView = Backbone.View.extend({
    el: '#books',
    // className: "large-4 medium-4  cell ",

   events:{
      	 "click #testBtn" : "testBtnFunc",
      	 "click #testBtn2" : "helloWorld",
      	 "submit #addBookForm" : "addBook",
      	 "click #myDivBtn" : "myDivBtn"
      		 
    },
    
    myDivBtn: function(e){
    	alert("myDivBtn clicked!!");
    },
    
    testBtnFunc: function(e){
    	alert("test button clicked!!");
    },
    
    helloWorld : function(e){
    	alert("Hello World !!");
    },
    
    addBook: function(e){
    	e.preventDefault();
    	alert("form submitted");
    },
    
    initialize: function( initialBooks ) {
        this.collection = new app.Library( initialBooks );
        this.listenTo( this.collection, 'add', this.renderBook );
        this.render();
    },

    // render library by rendering each book in its collection
    render: function() {
        this.collection.each(function( item ) {
            this.renderBook( item );
        }, this );
    },

    // render a book by creating a BookView and appending the
    // element it renders to the library's element
    renderBook: function( item ) {
        var bookView = new app.BookView({
            model: item
        });
        this.$el.append( bookView.render().el );
    }
});