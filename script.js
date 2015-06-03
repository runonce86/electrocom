jQuery( function() {

	// Something...
});

jQuery( document ).on( 'click', '.insert-media', function( event ) {

	wp.media.editor.send.attachment = function( props, attachment ) {

		for( var i in elcom_images ) {

			var current = elcom_images[i];

			if( i == attachment.id ) {

				var image = current;
			}
		}

		var img = document.createElement( 'img' );
		jQuery( img ).attr( 'src', image[0] );
		jQuery( '#product_images .inside' ).append( img );
	}
});
