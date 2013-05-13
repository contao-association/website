var Membership = new Class({

    Implements: [Events],
    Binds: ['toggleCustomContainer'],

    fieldset: null,

    initialize: function(id)
    {
        this.fieldset = document.id(id);
        this.fieldset.getElements('.radio, .tl_radio').addEvent('click', function(event) { this.toggleCustomContainer(event.target); }.bind(this));

        var current = this.fieldset.getElement('.radio:checked, .tl_radio:checked');

        if (current)
        {
            this.toggleCustomContainer(current);
        }
        else
        {
            this.hideCustomContainers();
        }
    },

    toggleCustomContainer: function(el)
    {
        this.hideCustomContainers();

        var id = el.get('id').replace(/_membership$/, '') + '_custom_container';

        if (document.id(id))
        {
            document.id(id).setStyle('display', 'block');
        }
    },

    hideCustomContainers: function()
    {
        this.fieldset.getElements('.custom_container').setStyle('display', 'none');
    }
});