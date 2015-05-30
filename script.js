jQuery( document ).on( 'click', '.insert-media', function( event ) {
	wp.media.editor.send.attachment = function( props, attachment ) {
		console.log( attachment );
	}
});
