'use strict';

(function($) {
  var media_uploader = null;

  $(function() {
    // ready
    condition_value();
  });

  $(document).on('change', '.wpcpc_condition_apply', function() {
    condition_value();
  });

  $(document).on('click touch', '.wpcpc_add_condition_btn', function(e) {
    e.preventDefault();
    var $this = $(this);

    $this.addClass('disabled');

    var data = {
      action: 'wpcpc_add_condition',
    };

    $.post(ajaxurl, data, function(response) {
      $('.wpcpc_conditions').append(response);
      condition_value();
      $this.removeClass('disabled');
    });
  });

  $(document).on('click touch', '.wpcpc_condition_remove', function(e) {
    e.preventDefault();
    $(this).closest('.wpcpc_condition').remove();
  });

  $(document).
      on('click touch', '#wpcpc_logo_select, #wpcpc_banner_select',
          function(event) {
            image_uploader(event, $(this));
          });

  $(document).on('click touch', '.wpcpc_remove_image', function(e) {
    var $wrap = $(this).closest('.wpcpc_image_uploader');

    $wrap.find('.wpcpc_image_val').val('');
    $wrap.find('.wpcpc_selected_image_img').html('');
    $wrap.find('.wpcpc_selected_image').hide();
  });

  // clear custom fields when collection is added
  if ($('body').hasClass('edit-tags-php') &&
      $('body').hasClass('taxonomy-wpc-collection')) {
    $(document).ajaxSuccess(function(event, xhr, settings) {
      // check ajax action of request that succeeded
      if (typeof settings != 'undefined' && settings.data &&
          ~settings.data.indexOf('action=add-tag') &&
          ~settings.data.indexOf('taxonomy=wpc-collection')) {
        $('.wpcpc_image_val').val('');
        $('.wpcpc_selected_image_img').html('');
        $('.wpcpc_selected_image').hide();
        $('.wpcpc_conditions').html('');
        $('#wpcpc_include').val('').trigger('change');
        $('#wpcpc_exclude').val('').trigger('change');
      }
    });
  }

  function condition_value() {
    $('.wpcpc_condition_value').each(function() {
      var $this = $(this);
      var apply = $this.closest('.wpcpc_condition').
          find('.wpcpc_condition_apply').
          val();

      $this.selectWoo({
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              q: params.term, action: 'wpcpc_search_term', taxonomy: apply,
            };
          }, processResults: function(data) {
            var options = [];
            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        }, minimumInputLength: 1,
      });
    });
  }

  function image_uploader(event, btn) {
    var $wrap = btn.closest('.wpcpc_image_uploader');

    media_uploader = wp.media({
      frame: 'post', state: 'insert', multiple: false,
    });

    media_uploader.on('insert', function() {
      var json = media_uploader.state().get('selection').first().toJSON();
      var image_id = json.id;
      var image_url = json.url;
      var image_html = '<img src="' + image_url + '"/>';

      $wrap.find('.wpcpc_image_val').val(image_id);
      $wrap.find('.wpcpc_selected_image').show();
      $wrap.find('.wpcpc_selected_image_img').html(image_html);
    });

    media_uploader.open();
  }
})(jQuery);
