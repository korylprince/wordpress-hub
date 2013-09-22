jQuery(document).ready(function(){
    wp.media.controller.HubImage = wp.media.controller.Library.extend({
        defaults: _.defaults({
            id:         'hub-image',
            filterable: 'uploaded',
            multiple:   false,
            toolbar:    'hub-image',
            title:      'Select Hub Image',
            priority:   60,  

            syncSelection: false
        }, wp.media.controller.Library.prototype.defaults ),

        initialize: function() {
            var library, comparator;

            // If we haven't been provided a `library`, create a `Selection`.
            if ( ! this.get('library') )
                this.set( 'library', wp.media.query({ type: 'image' }) );

            wp.media.controller.Library.prototype.initialize.apply( this, arguments );

            library    = this.get('library');
            comparator = library.comparator;

            // Overload the library's comparator to push items that are not in
            // the mirrored query to the front of the aggregate collection.
            library.comparator = function( a, b ) {
                var aInQuery = !! this.mirroring.get( a.cid ),
                    bInQuery = !! this.mirroring.get( b.cid );

                if ( ! aInQuery && bInQuery )
                    return -1;
                else if ( aInQuery && ! bInQuery )
                    return 1;
                else 
                    return comparator.apply( this, arguments );
            };   

        }, 

        activate: function() {
            this.updateSelection();
            this.frame.on( 'open', this.updateSelection, this );
            wp.media.controller.Library.prototype.activate.apply( this, arguments );
        },

        deactivate: function() {
            this.frame.off( 'open', this.updateSelection, this );
            wp.media.controller.Library.prototype.deactivate.apply( this, arguments );
        },

        updateSelection: function() {
            var selection = this.get('selection'),
                id = wp.media.view.settings.post.hubImageId,
                attachment;
            if ( '' !== id && -1 !== id ) {
                attachment = wp.media.model.Attachment.get( id );
                attachment.fetch();
            }

            selection.reset( attachment ? [ attachment ] : [] );
        }
    });

    wp.media.hubImage = { 
        get: function() {
            return wp.media.view.settings.post.hubImageId;
        },  

        set: function( id ) { 
            var settings = wp.media.view.settings;

            settings.post.hubImageId = id; 
            
            wp.media.post( 'set-hub-image', {
                json:         true,
                post_ID:      settings.post.id,
                post_type: jQuery("#hub_uploader").data('type'),
                _hub_image: settings.post.hubImageId,
                hub_nonce: jQuery("#hub_nonce").val() 
            }).done( function( html ) {
                jQuery( '#hub_uploader' ).html( html );
            });
        },

        frame: function() {
            if ( this._frame )
                return this._frame;

            this._frame = wp.media({
                state: 'hub-image',
                states: [ new wp.media.controller.HubImage() ]
            }); 

            this._frame.on( 'toolbar:create:hub-image', function( toolbar ) { 
                this.createSelectToolbar( toolbar, {
                    text: 'Select Hub Image'
                }); 
            }, this._frame );

            this._frame.state('hub-image').on( 'select', this.select );
            return this._frame;
        },  

        select: function() {
            selection = this.get('selection').single();
            wp.media.hubImage.set( selection ? selection.id : -1 );
        },
        init: function() {
            // Open the content media manager to the 'hub image' tab when
            // the post thumbnail is clicked.
            jQuery('#hub').on( 'click', '#hub_add, #hub_image', function( event ) {
                event.preventDefault();
                // Stop propagation to prevent thickbox from activating.
                event.stopPropagation();

                wp.media.hubImage.frame().open();

            // Update the hub image id when the 'remove' link is clicked.
            }).on( 'click', '#hub_remove', function() {
                wp.media.hubImage.set( -1 );
            });
        }
    };
    //propagate settings
    wp.media.view.settings.post.hubImageId = jQuery("#hub_image").data("id") || -1;
    jQuery( wp.media.hubImage.init );
});
