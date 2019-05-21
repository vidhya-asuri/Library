// site/js/models/book.js

var app = app || {};

app.PO = Backbone.Model.extend({
    defaults: {
    	name: '', // name of the person entering the purchase order.
        email: '',  // email of the person entering the purchase order.
        phone: '',
        reason : '',
        amount : '0', // estimated total cost of the purchase order
        userLocation : '',
        po_num: 0,
        loc_id: 0,
        dept_id: 0,
        timestamp: 0,
        vendor_name: '',
        description: '',
        purchase_location: '',
        status: 10,
        credits_required: 0,
        access_code: 0
    },
    urlRoot: "localhost"
});