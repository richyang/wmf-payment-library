( function( $ ) {     
		$.getDynamicFormElements = function(){
                var tracking_data = {"url": escape(window.location), "pageref": escape(document.referrer)};

                var processFormElements = function (data, status){
                        $('input[name=orderid]').val(data['dynamic_form_elements']['orderid']);
                        $('input[name=token]').val(data['dynamic_form_elements']['token']);
                        $('input[name=contribution_tracking_id]').val(data['dynamic_form_elements']['contribution_tracking_id']);
                        $('input[name=utm_source]').val(data['dynamic_form_elements']['tracking_data']['utm_source']);
                        $('input[name=utm_medium]').val(data['dynamic_form_elements']['tracking_data']['utm_medium']);
                        $('input[name=utm_campaign]').val(data['dynamic_form_elements']['tracking_data']['utm_campaign']);
                        $('input[name=referrer]').val(data['dynamic_form_elements']['tracking_data']['referrer']);
                        $('input[name=language]').val(data['dynamic_form_elements']['tracking_data']['language']);
                };

                $.post( wgScriptPath + '/api.php', {
                            'action' : 'pfp',
                            'dispatch' : 'get_required_dynamic_form_elements',
                            'format' : 'json',
                            'tracking_data' : '{"url": "'+escape(window.location)+'", "pageref": "'+escape(document.referrer)+'"}'
                        }, processFormElements, 'json' );
        };

        return $( this );

} )( jQuery );

if( String(window.location).indexOf('_nocache_') == -1 ){
	jQuery( document ).ready( jQuery.getDynamicFormElements );
}