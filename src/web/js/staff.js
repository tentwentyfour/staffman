
$('#profile').on('click', 'button.btn-edit-profile', function() {

    $('.text').toggle();
    $('.input').toggle();

});

$('#profile').on('click', 'button.btn-alias-modal', function() {

    $('#modalMailAlias').modal('show');
    $('#newAlias')
        .val('')
        .focus();

});

$('#profile').on('click', 'button.btn-reset-password', function() {

    uid = $('#profile').data('uid');

    var request = $.ajax({
        type: 'post',
        url: '/person/' + uid + '/resetPassword'

    }).done( function( resp ){

        console.log('password reset');

    })

});

$(document).off('keypress').on('keypress', function(e){
    if( e.keyCode == 97 && $('#modalMailAlias').is(':hidden')) {
        $('button.btn-alias-modal').trigger('click');
        return false;
    }
});


$(document).on('click', 'button.btn-change-password-modal', function() {

    $('#modalChangePassword').modal('show');
    $('#newPassword')
        .val('')
        .focus();
    $('#newPassword2').val('');

});

$('#newPassword2').keypress(function(e) {
    if(e.which == 13) {
        $('button.btn-save-new-password').click();
    }
});

$('#modalChangePassword').on('click', 'button.btn-save-new-password', function() {

    if ( $('#newPassword').val() == $('#newPassword2').val() ) {

        uid         =   $('#uid').data('uid');
        newPassword = $('#newPassword2').val();

        var request = $.ajax({
            type: 'post',
            url: '/person/' + uid + '/changePassword',
            data: {
                password:   newPassword
            }

        }).done( function( resp ){

            $('#modalChangePassword').modal('hide');

        })

    } else {

        console.log('passwords did not match');

    }

});

$('#newAlias').keypress(function(e) {
    if(e.which == 13) {
        $('button.btn-add-alias').click();
    }
});

$('#profile').on('focusout', 'input', function() {
    uid         = $('#profile').data('uid');
    attribute   = $(this).data('name');
    value       = $(this).val();

    var request = $.ajax({
        type: 'post',
        url: '/person/' + uid + '/edit',
        data: {
            attribute:  attribute,
            value:      value
        }
    }).done( function( resp ){

        console.log( resp );

    })
});

$('#modalMailAlias').on('click', 'button.btn-add-alias', function() {
    uid         = $('#profile').data('uid');
    attribute   = 'mailalias';
    value       = $('#newAlias').val();

    var request = $.ajax({
        type: 'post',
        url: '/person/' + uid + '/addAttribute',
        data: {
            attribute:  attribute,
            value:      value
        }
    }).done( function( resp ){

        console.log( resp );
        $( 'button.btn-add-alias' ).text( resp.status );
        $('#modalMailAlias').modal('hide');

        var newAlias = '<span class="label label-info" id="alias-' + value + '">' +
            '<span class="glyphicon glyphicon-remove input" style="display: none;" data-alias="' + value + '"></span> ' + value + '</span>';

        $('#aliases').append(newAlias);

    })
});

$('#aliases').on('click', 'span.glyphicon-remove', function() {
    uid     =   $('#profile').data('uid');
    alias   =   $(this).data('alias');

    var request = $.ajax({
        type: 'post',
        url: '/person/' + uid + '/removeAttribute',
        data: {
            attribute:  'mailalias',
            value:      alias
        }
    }).done( function( resp ){

        $('#alias-' + alias ).hide();

    })

});