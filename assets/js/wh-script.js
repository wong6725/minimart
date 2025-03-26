jQuery( function( $ ) 
{
	var wcwh = ajax_wh;
	var formCache = [];
	var chart;

	//unique id
	var uniqId = (function(){
	    var i=0;
	    return function() {
	        return i++;
	    }
	})();

	//blockUI
	function blocking( elem )
	{
		elem.block(
		{
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
	}
	function unblocking( elem )
	{
		elem.unblock();
	}

	contentMinHeight();
	function contentMinHeight()
	{
		$( '#wpbody-content' ).css( 'min-height', $( '#adminmenu' ).height()+'px' )
	}

	//tabs
	tabs();
	function tabs()
	{
		$( ".tabbing" ).tabs();
	}

	//Sortable
	sortable();
	function sortable()
	{
		$( ".sortable_row" ).sortable({
			handle: ".handle",
		  	cursor: "grabbing", 
		  	items: 'tr:not(".follow_dragged")', 
		  	update: function( event, ui ) 
		  	{	
		  		$( this ).find( '.dragged_row' ).each( function( index ) 
				{
					var seq = $( this ).data( 'seq' );
					
					if( $( '.row'+seq+'.follow_dragged' ).length )
					{
						var html = $( '.row'+seq+'.follow_dragged' ).clone();
						$( '.row'+seq+'.follow_dragged' ).remove();
						$( '.row'+seq+'.dragged_row' ).after( html );
					}
				});

				var c = 1;
				$( this ).find( '.dragged_row .sortable_item_number' ).each(function( index ) {
				  	$( this ).val( c ); c++;
				});	
		  	}
		});
	}


	//Tooltip
	tooltip();
	function tooltip()
	{
		$( '[data-toggle="tooltip"], .toolTip' ).tooltip();
	}


	//readonly handler
	readonly();
	function readonly()
	{
		if( $( '.readonly' ).length > 0 )
		{
			$( 'form input.readonly' ).attr( 'readonly', 'readonly' );
			$( 'form textarea.readonly' ).attr( 'readonly', 'readonly' );
			$( 'form select.readonly' ).attr( 'disabled', 'disabled' );
		}
	}

	//number only input
	numonly();
	function numonly()
	{
		$( "input.numonly" ).numeric();
		$( "textarea.numonly" ).numeric();

		$( "input.integer" ).numeric( {decimal: false} );
		$( "textarea.integer" ).numeric( {decimal: false} );

		$( "input.positive-number" ).numeric( {negative: false} );
		$( "textarea.positive-number" ).numeric( {negative: false} );
		
		$( "input.numonly.positive-integer" ).numeric( {decimal: false, negative: false} );
		$( "textarea.numonly.positive-integer" ).numeric( {decimal: false, negative: false} );
	}


	//min / max number
	$( document ).on( "keyup change", "input.minmaxnum", function() 
	{
		var elem = $( this );
		var min = Number( elem.attr( 'min' ) );
		min = ( typeof min !== 'undefined' )? min : 1;
		var max = Number( elem.attr( 'max' ) );
		max = ( typeof max !== 'undefined' )? max : 999;

		var value = Number( elem.val() );
		var text = value.toString();
		if( value < min ) elem.val( text.substr(0,text.length - 1) );
		else if( value > max ) elem.val( text.substr(0,text.length - 1) );
	});


	//Select2
	selectTwo();
	function selectTwo()
	{
		$('input.select2, select.select2').select2(
		{
			allowClear: true,
		});
		$('input.select2Strict, select.select2Strict').select2(
		{
			allowClear: false,
		});
		$('input.select2Tag, select.select2Tag').select2(
		{
			tags: true,
			allowClear: true,
		});
	}
	function modalSelectTwo()
	{
		$('#wcwhModalForm input.select2, #wcwhModalForm select.select2').select2(
		{
		    dropdownParent: $( "#wcwhModalForm" ),
		    allowClear: true,
		});
		$('#wcwhModalForm input.select2Strict, #wcwhModalForm select.select2Strict').select2(
		{
		    dropdownParent: $( "#wcwhModalForm" ),
		    allowClear: false,
		});
		$('#wcwhModalForm input.select2Tag, #wcwhModalForm select.select2Tag').select2(
		{
		    tags: true,
		    dropdownParent: $( "#wcwhModalForm" ),
		    allowClear: true,
		});
		
		$('#wcwhModalImEx input.select2, #wcwhModalImEx select.select2').select2(
		{
		    dropdownParent: $( "#wcwhModalImEx" ),
		    allowClear: true,
		});
		$('#wcwhModalImEx input.select2Strict, #wcwhModalImEx select.select2Strict').select2(
		{
		    dropdownParent: $( "#wcwhModalImEx" ),
		    allowClear: false,
		});
		$('#wcwhModalImEx input.select2Tag, #wcwhModalImEx select.select2Tag').select2(
		{
		    tags: true,
		    dropdownParent: $( "#wcwhModalImEx" ),
		    allowClear: true,
		});
	}
	$( document ).on( 'paste', '.select2-search__field', function (e)
	{
		e.preventDefault();

		var $select = $( e.target ).closest( '.select2' ).prev();
	    var clipboard = (e.originalEvent || e).clipboardData.getData('text/plain');
	    var segments = clipboard.split( new RegExp( "\\s|,|;" ) );

	    var vals = [];
	    if( $select.hasClass( 'select2Tag' ) && $select.hasClass( 'select2Empty' ) )
		{	
			$select.find('option').remove();
			$.each( segments, function ( key, value )
			{
				value = $.trim( value );
				if( value.length > 0 )
				{
					$select.append('<option value="'+value+'">'+value+'</option>');
					vals.push( value );
				}
			} );
		}
		else
		{

			$( $select ).find( 'option' ).each( function( index ) 
			{
				var $opt = $( this );
				$.each( segments, function ( key, value )
				{
					value = $.trim( value );
					if( $opt.html().toLowerCase().includes( value.toLowerCase() ) && value.length > 0 )
					{
						vals.push( $opt.val() );

						return;
					}
				} );
			});
		}

		$( $select ).val( vals ).trigger( 'change' );
	});

	//modal Multi Select
	modalSelect();
	function modalSelect()
	{
		$( 'select.select2.modalSelect' ).each( function( index ) 
		{
			var elem = $(this);
			if( elem.prop('multiple') && ! elem.parent().find( '.selectModal' ).length )
			{
				var id = uniqId();
				elem.parent().find( '.select2.select2-container' ).prepend('<a class="selectModal btn btn-xs" title="Select Options" data-refid="mOpt_'+id+'"><i class="fa fa-list-ul"></i></a>');
				elem.addClass( 'mOpt_'+id );
			}
		});
	}
	$( document ).on( 'click', '.selectModal', function()
	{
		var elem = $(this);
		var modalElem = $( '#wcwhModalOpts' );
		var def_content = $( '#modalOptionTPL' ).html();
		var id = elem.data( 'refid' );

		var $source = elem.parents( '.select2.select2-container' ).parent();

		if( typeof( def_content ) !== 'undefined' )
		{
			modalElem.modal( { show:true } );
			
			var all_content = '';
			$source.find( 'select option' ).each( function( index ) 
			{
				var content = def_content;

				var regex = new RegExp( '{value}', 'g' );
				content = content.replace( regex, $(this).val() );

				regex = new RegExp( '{title}', 'g' );
				content = content.replace( regex, $(this).html() );

				if( $(this).is(':selected') )
				{
					regex = new RegExp( '{attr}', 'g' );
					content = content.replace( regex, 'checked' );
				}

				all_content+= content;
			});

			all_content = '<div class="selectOptions">'+all_content+'</div>';
			modalElem.find( '.modal-body' ).append( all_content );
		}

		modalElem.data( 'refid', id );

		modalElem.find( '.modal-title' ).html( $source.find( 'label' ).html() );
		var all = '<a class="btn selectModalAll toolTip" title="Select All"><i class="fa fa-check-square"></i> All</a>';
		var clear = '<a class="btn selectModalClear toolTip" title="Clear"><i class="fa fa-minus-square"></i> Clear</a>';
		var search = '<br><input type="text" class="selectModalSearch form-control form-control-sm onfocusFocus" placeholder="Find" >';
		modalElem.find( '.message' ).html( 'Selection: '+all+clear+search );
		modalElem.find( '.message input.selectModalSearch' ).focus();
		
		var action = [ 'cancel', 'done' ];
		$.each( action, function ( key, value )
		{
			var btn = modalElem.find( '.footer-action-'+value ).html();
			modalElem.find( '.modal-footer' ).append( btn );
		} );

		//$( document ).trigger( 'onDynamicElement' );
	});
	$( document ).on( "keyup change paste", "input.selectModalSearch", function() 
	{	
		var value = $(this).val().trim().toLowerCase();
		var segments = value.split( new RegExp( "\\s|,|;" ) );

		$( this ).parents( '.modal-content' ).find( ".modal-body .selectOptions .modalOptSect" ).filter( function() 
		{
			if( typeof( segments ) != 'undefined' )
			{
				var $row = $( this );
				var c = 0;
				$.each( segments, function ( key, val )
				{
					if( $row.text().toLowerCase().indexOf( val ) > -1 ) c++;
				} );

				$( this ).toggle( c > 0 );
			}
			else
				$( this ).toggle( $( this ).text().toLowerCase().indexOf( value ) > -1 );
		});

		listCount();
	});
	$( document ).on( 'click', '.selectModalAll', function()
	{
		$( this ).parents( '#wcwhModalOpts' ).find( '.modalOptSect input:visible' ).prop( "checked", true );
	});
	$( document ).on( 'click', '.selectModalClear', function()
	{
		$( this ).parents( '#wcwhModalOpts' ).find( '.modalOptSect input' ).prop( "checked", false );
	});
	$( document ).on( 'click', '.modalOpts .action-yes', function()
	{
		var elem = $(this);
		var modalElem = elem.parents( '#wcwhModalOpts' );
		var id = modalElem.data( 'refid' );

		var limit = $( 'select.select2.modalSelect.'+id ).data( 'maximum-selection-length' );
		limit = ( typeof( limit ) !== 'undefined' && limit > 0 )? limit : 0;

		var vals = []; var c = 1;
		elem.parents( '#wcwhModalOpts' ).find( '.modalOptSect input:checked' ).each( function( index ) 
		{
			if( limit > 0 && c >= limit ) return;
            vals.push( $(this).val() );
            c++;
		});
		
		$( 'select.select2.modalSelect.'+id ).val( vals ).trigger( 'change' );
	});


	//Show Hide Option
	showHide();
	function showHide()
	{
		$( '.optionShowHide' ).each( function( index ) 
		{
			$( this ).trigger('change');
		});
	}
	$( document ).on( 'change', '.optionShowHide', function()
	{
		var elem = $( this );
		var value = elem.val();
		var target = elem.data( 'showhide' );

		$( target ).each( function( index ) 
		{
			$( this ).val(null).trigger('change');
			if( $( this ).hasClass( value ) )
			{
				$( this ).show();
			}
			else
			{
				$( this ).hide();
			}
		});
	} );


	//Scroll
	$( 'a.scrollTo' ).on( 'click', function()
	{
		$("html, body").animate( { scrollTop: $( $( this ).data( 'target' ) ).offset().top }, 400 );
	} );


	//List Numbering
	function listNum()
	{
		if( $( '.detail-container table.details' ).length && $( '.detail-container table.details thead .num' ).length )
		{
			$( '.detail-container table.details' ).each( function( index ) 
			{
				var num = 0;
				$( this ).find( 'tbody tr' ).each( function( index ) 
				{
					if( $( this ).find( '.num' ).length )
					{
						num++;
						$( this ).find( '.num' ).html( num+'.' );	
					}
				} );
			} );
		}
	}


	//List Count
	listCount();
	function listCount()
	{
		if( $( '.listing-form .wp-list-table' ).length && $( '.line-items' ).length )
		{
			 $( '.line-items' ).html( $( '.listing-form .wp-list-table.filterable tbody tr:visible' ).length );
		}
	}
	

	//Dynamic element generated
	$( document ).bind( 'onDynamicElement', function()
	{
		readonly();
		tabs();
		numonly();
		selectTwo();
		modalSelectTwo();
		modalSelect();
		tooltip();
		pickDate();
		tickGroup();
		showHide();
		listNum();
		listCount();
		sortable();
	} );


	//enter next row
	$( document ).on( 'keyup', '.modal table tbody .enterNextRow', function(e)
	{
	    if( e.keyCode == 13 )
	    {
	    	var tbody = $( this ).closest( "tr" ).parent();
	        var trIdx = $( this ).closest( "tr" ).index();
	        var tdIdx = $( this ).closest( "td" ).index();

	        trIdx++;
	        while( tbody.find( 'tr:eq('+trIdx+')' ).length )
	        {
	        	if( tbody.find( 'tr:eq('+trIdx+') td:eq('+tdIdx+') .enterNextRow' ).length )
	        	{	
	        		tbody.find( 'tr:eq('+trIdx+') td:eq('+tdIdx+') .enterNextRow' ).focus();
	        		return;
	        	}
	        	trIdx++;
	        }
	    }
	});

	//keyboard control
	$( document ).on( 'keyup', function(evt) {
		if( evt.keyCode === 27 && $( '.modal.main' ).hasClass( 'show' ) )
        {
            evt.preventDefault();

            $( '.modal.main.show' ).find( '.close' ).click();
        }
	});
	

	//focus & select field
	$( document ).on( 'focus', 'input.onfocusFocus', function()
	{
		$( this ).select();
	} );

	//focus & select field
	$( document ).on( 'focus', 'input.onfocusClear', function()
	{
		$( this ).val( '' ).trigger( 'change' );
	} );


	//Tick Group Checkbox
	tickGroup();
	function tickGroup()
	{
		$( '.tick-all' ).each( function( index ) {
			var elem = $( this );

			var source = ( typeof( elem.data( 'closest' ) ) !== 'undefined' && elem.data( 'closest' ) )? elem.data( 'closest' ) : '';
			var target = ( typeof( elem.data( 'find' ) ) !== 'undefined' && elem.data( 'find' ) )? elem.data( 'find' ) : '';

			if( source && target )
			{	
				var count = 0; var checked = 0;
				elem.closest( source ).find( target+" input[type='checkbox']" ).each( function( index ) {
					count++;
					if( $( this ).is(':checked') ) checked++;
				});

				if( checked == count ) elem.prop( "checked", true );
				else elem.prop( "checked", false );
			}
		} );
	}
	$( document ).on( 'change', '.tick-all', function()
	{
		var source = ( typeof( $( this ).data( 'closest' ) ) !== 'undefined' && $( this ).data( 'closest' ) )? $( this ).data( 'closest' ) : '';
		var target = ( typeof( $( this ).data( 'find' ) ) !== 'undefined' && $( this ).data( 'find' ) )? $( this ).data( 'find' ) : '';

		if( source && target )
		{	
			if( $( this ).is(':checked') )
				$( this ).closest( source ).find( target+" input[type='checkbox']" ).prop( "checked", true );
			else
				$( this ).closest( source ).find( target+" input[type='checkbox']" ).prop( "checked", false );
		}
	} );


	//graph initiate
	$( document ).ready(function() 
	{
		var target = $( '#graphCanvas' );

	    if( target )
	    {
	    	var pageLoadChart = target.attr( 'data-pageLoadChart' );
	    	if( pageLoadChart )
	    		$( '#search-submit' ).trigger( 'click' );
	    }
	});


	// Notification
    var msgCloseOn = wcwh.float_timer * 1000;
    var timeoutElem = [];

    // Initialize messages on page load
    onInitMsg();

    function onInitMsg() {
        $( '.notice-message' ).each(function(index) {
            notifyMsg( $(this) );
        });
    }

    // Create a MutationObserver instance to observe added nodes
    const observer = new MutationObserver(function(mutationsList, observer) {
        mutationsList.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && $(node).hasClass('notice-message')) {
                        // Call notifyMsg for each new notice-message element
                        notifyMsg($(node));
                    }
                });
            }
        });
    });

    // Configuration for the observer: observe the entire document for added nodes
    const config = {
        childList: true,   // Observe the addition/removal of child nodes
        subtree: true      // Observe all descendants of the target node
    };

    // Start observing the document
    observer.observe(document.body, config);

    // Notification handler
    function notifyMsg(elem) {
        if (!elem.hasClass('notice-message')) return;

        if ((elem.hasClass('is-dismissible') || elem.hasClass('dismissible')) && !elem.find('.notice-close').length) {
            elem.append("<a class='notice-close'><i class='fa fa-times-circle'></i></a>");
        }

        // Handle timeout and clearing existing timeouts based on 'seq' attribute
        if (typeof(elem.attr('seq')) !== 'undefined' && elem.attr('seq').length) {
            var pr = elem.attr('seq');
            clearTimeout(timeoutElem[pr]);
            timeoutElem[pr] = null;
            delete timeoutElem[pr];
        }

        // Generate a random sequence number for the notification
        var r = Math.floor(Math.random() * 100000);
        elem.attr('seq', r);

        // Set timeout to fade out the notification
        timeoutElem[r] = setTimeout(function() {
            elem.fadeOut();
        }, msgCloseOn);
    }

    // Close the notification on click of the close button
    $(document).on('click', '.notice-message .notice-close', function() {
        $(this).closest('.notice-message').fadeOut();
    });

    // Pause the fade-out timeout when mouse enters the notification
    $(document).on('mouseenter', '.notice-message', function() {
        var r = $(this).attr('seq');
        if (typeof(r) === 'undefined') return;

        clearTimeout(timeoutElem[r]);
        timeoutElem[r] = null;
        delete timeoutElem[r];
    });

    // Restart the fade-out timeout when mouse leaves the notification
    $(document).on('mouseleave', '.notice-message', function() {
        var elem = $(this);
        notifyMsg(elem);
    });
    //End


	//Search filter on table row
	let filterTime = 0;
	$( document ).on( "keyup change paste", "input.searchFiltering", function() 
	{
		clearTimeout(filterTime);

		var elem = $(this);
		filterTime = setTimeout( function() 
		{
	        var value = elem.val().trim().toLowerCase();
	        var segments = value.split( new RegExp( "\\s|,|;" ) );

			elem.closest( '.wcwh-section' ).find( ".filterable #the-list tr" ).filter( function() 
			{
				if( typeof( segments ) != 'undefined' )
				{
					var $row = $( this );
					var c = 0;
					$.each( segments, function ( key, val )
					{
						if( $row.text().toLowerCase().indexOf( val ) > -1 ) c++;
					} );

					$( this ).toggle( c > 0 );
				}
				else
					$( this ).toggle( $( this ).text().toLowerCase().indexOf( value ) > -1 );
			});

			listCount();
	    }, 700);
	});

	//Trigger CHange
	$( document ).on( "change", "select.triggerChange", function()
	{
		var target = $( this ).data( 'change' );
		
		if( $( this ).val() && $( this ).val().length && typeof( target ) !== 'undefined' )
		{
			$( target ).click();
			$( this ).val( '' ).trigger( 'change' );
		}
	} );
	
	$(document).on("keyup","input.inputSearch",function (e) 
	{
		if (e.key === 'Enter' || e.keyCode === 13) 
		{
			var target = $( this ).data( 'change' );
			if( $( this ).val() && $( this ).val().length && typeof( target ) !== 'undefined' )
			{
				$( target ).click();
				$( this ).val( '' ).trigger( 'change' );
			}
		}
	});

	//Dynamic Country State
	$( document ).on( "change", "select.dynamicCountryState", function()
	{
		var $elem = $( this );
		var country = $elem.val();
		var stateTarget = $elem.data( 'state_target' );

		var param = {
			'action' 	: wcwh.appid+'_dynamicCountryState',
			'country'	: country,
		};

		var $block = $elem.parents( '.modal-content' );
		if( ! $block ) $block = $( '.wcwh-section' );
		blocking( $block );

		$.ajax({
			type:    'POST',
			url:     wcwh.ajax_url,
			data:    param,
			beforeSend: function()
			{
			},
			success: function( outcomes ) 
			{
				unblocking($block);
				if ( outcomes ) {
					var outcome = $.parseJSON( outcomes );
					
					if( outcome.succ ) 
					{
						if( outcome.state )
						{
							$( stateTarget ).find('option').remove();
							$.each( outcome.state, function ( key, value )
							{
								$( stateTarget ).append( '<option value="'+key+'">'+value+'</option>');
							} );
						}
					}
				}
			},
			error: function( e, code, msg )
			{
				
			},
			complete: function( e, stat )
			{
				
			}
		});
	} );


	//External Scanner Device
	$( document ).anysearch( 
	{
        searchSlider: false,
        searchFunc: function (search) {
            if( search.length > 0 )
            {
            	var text = search.trim();

            	if( $( '#wcwhModalForm' ).hasClass( 'show' ) && text.length )
            	{
            		if( text.length == wcwh.iserial_match )
            		{
            			var weight = text.substring( text.length - wcwh.iserial_weight );
            			weight = weight / wcwh.iweight_dividen;
            			var text = text.substring( 0, wcwh.iserial_length );
            		}
            		
            		if( $( '#wcwhModalForm .canScanBarcode' ).length && $( '#wcwhModalForm .canScanBarcode' ).hasClass( 'immediate' ) )
            		{
            			var sku = $( "#wcwhModalForm .canScanBarcode option[data-sku='"+text+"']" ).attr( 'value' );
            			var code = $( "#wcwhModalForm .canScanBarcode option[data-code='"+text+"']" ).attr( 'value' );
            			var serial = $( "#wcwhModalForm .canScanBarcode option[data-serial='"+text+"']" ).attr( 'value' );

            			var value = ( typeof( serial ) !== 'undefined' && serial )? serial : '';
            			value = ( typeof( code ) !== 'undefined' && code )? code : value;
            			value = ( typeof( sku ) !== 'undefined' && sku.length )? sku : value;

            			$( '#wcwhModalForm .canScanBarcode' ).val( value ).trigger( 'change' );
            			$( '#wcwhModalForm .dynamic-action' ).click();
            		}
            		else if( $( '#wcwhModalForm .canScanBarcode' ).length && !$( '#wcwhModalForm .canScanBarcode' ).hasClass( 'immediate' ) )
            		{
            			var sku = $( "#wcwhModalForm .canScanBarcode option[data-sku='"+text+"']" ).attr( 'value' );
            			var code = $( "#wcwhModalForm .canScanBarcode option[data-code='"+text+"']" ).attr( 'value' );
            			var serial = $( "#wcwhModalForm .canScanBarcode option[data-serial='"+text+"']" ).attr( 'value' );

            			var value = ( typeof( serial ) !== 'undefined' && serial )? serial : '';
            			value = ( typeof( code ) !== 'undefined' && code )? code : value;
            			value = ( typeof( sku ) !== 'undefined' && sku.length )? sku : value;

            			if( $( '#wcwhModalForm .canScanBarcode' ).hasClass( 'multiple' ) )
            			{
            				var vals = $( '#wcwhModalForm .canScanBarcode' ).val();
            				vals = ( vals )? vals : [];
            				vals.push( value );
            				$( '#wcwhModalForm .canScanBarcode' ).val( vals ).trigger( 'change' );
            			}
            			else
            			{
            				$( '#wcwhModalForm .canScanBarcode' ).val( value ).trigger( 'change' );
            			}
            		}
            	}
            	else if($( '.barcodeTrigger' ).length)
				{
				    var uid = $( " .barcodeTrigger option[data-uid='"+text+"']" ).attr( 'value' );
				    var code = $( " .barcodeTrigger option[data-code='"+text+"']" ).attr( 'value' );
				    var serial = $( " .barcodeTrigger option[data-serial='"+text+"']" ).attr( 'value' );

				    var value = ( typeof( uid ) !== 'undefined' && uid )? uid : '';
				    value = ( typeof( code ) !== 'undefined' && code )? code : value;
				    value = ( typeof( serial ) !== 'undefined' && serial )? serial : value;

				    if(value)$( '.barcodeTrigger' ).val( value ).trigger( 'change' );
				        			
				}
            	else
            	{
            		if( text.length == wcwh.iserial_match )
            		{
            			var weight = text.substring( text.length - wcwh.iserial_weight );
            			weight = weight / wcwh.iweight_dividen;
            			var text = text.substring( 0, wcwh.iserial_length );
            		}
            		else if( text.length == wcwh.ibatch_match )
            		{
            			var text = text.substring( 0, wcwh.ibatch_length );
            		}
            		$( '#s-search-input' ).val( text ).trigger( 'change' );
            	}
            }
        },
    } );


    //Print
    function printElem(divId) {
        var content = document.getElementById(divId).innerHTML;
        var mywindow = window.open('', '_blank');

        mywindow.document.write('<html><head><title>Print</title>');
        mywindow.document.write('</head><body>');
        mywindow.document.write(content);
        mywindow.document.write('</body></html>');

        mywindow.document.close();
        mywindow.focus()
        mywindow.print();
        mywindow.close();
        return true;
    }
    $( document ).on( 'click', '.modal button.action-print', function()
	{
		if( $( this ).closest( '.modal' ).find( '.printable' ).length )
		{
			var elem = $( this ).closest( '.modal' ).find( '.printable' );
			var id = elem.attr( 'id' );
			printElem(id);	
		}
	} );


	//js Barcode | Qr Code
	function qr_barcode( args )
	{
		$('#barcode').html("");
		$('#qrcode').html("");

		if( ! args.code ) return false;
		
		var code = args.code; code = code.toString();
		var type = args.type;
        
        switch( type )
        {
        	case 'barcode':
        		$('#qrcode').hide();
            	$('#barcode').show();
           		JsBarcode( "#barcode", code, 
           		{
           			format: ( typeof( args.format ) !== 'undefined' )? args.format : 'qr',
           			displayValue: false,
           			width:( typeof( args.width ) !== 'undefined' )? args.width : 2,
           		} );
        	break;
        	case 'qr':
        	default:
        		$( '#qrcode' ).show();
	            $( '#barcode' ).hide();
	            var qrcode = new QRCode( "qrcode", 
	            {
	                text: code,
	                width: 128,
	                height: 128,
	                colorDark : "#000000",
	                colorLight : "#ffffff",
	                correctLevel : QRCode.CorrectLevel.L
	            });
        	break;
        }
	}
	$( document ).on( "click", ".jsPrint", function(e)
	{
		e.preventDefault();

		var elem = $( this );
		var type = ( typeof( elem.data( 'print_type' ) ) !== 'undefined' )? elem.data( 'print_type' ) : 'qr';
		var code = ( typeof( elem.data( 'code' ) ) !== 'undefined' )? elem.data( 'code' ) : '';
		var bc_format = ( typeof( elem.data( 'format' ) ) !== 'undefined' )? elem.data( 'format' ) : 'CODE128';
		var width = ( typeof( elem.data( 'width' ) ) !== 'undefined' )? elem.data( 'width' ) : 2;
		var args = { type: type, code : code, format: bc_format, width: width };
		qr_barcode( args );

		setTimeout(function () {
            printElem( 'code_area' );
        } ,500);
	} );
    $( document ).bind( 'onModalFormLoaded', function( e, modalElem, elem )
	{
		if( typeof( elem ) === 'undefined' || typeof( modalElem ) === 'undefined' ) return;

		if( elem.hasClass( 'jsTpl' ) )
		{	
			var type = ( typeof( elem.data( 'print_type' ) ) !== 'undefined' )? elem.data( 'print_type' ) : 'qr';
			var code = ( typeof( elem.data( 'code' ) ) !== 'undefined' )? elem.data( 'code' ) : '';
			var bc_format = ( typeof( elem.data( 'format' ) ) !== 'undefined' )? elem.data( 'format' ) : 'CODE128';
			var width = ( typeof( elem.data( 'width' ) ) !== 'undefined' )? elem.data( 'width' ) : 2;
			var args = { type: type, code : code, format: bc_format, width: width };
			qr_barcode( args );

			var data = elem.data();
			var content = modalElem.find( '.printable' ).html();
			if( typeof( content ) !== 'undefined' && typeof( data ) !== 'undefined' )
			{
				$.each( data, function ( key, value )
				{
					if( key.substring( 0, 4 ) == 'data' )
					{
						var k = key.substring( 4 );
						var regex = new RegExp( '{'+k+'}', 'g' );
						content = content.replace( regex, value );
					}
				} );
				modalElem.find( '.printable' ).html( content );

				if( modalElem.find( '.placeqrbarcode' ).length )
				{
					setTimeout( function () {
           				modalElem.find( '.placeqrbarcode' ).html( $( "#code_area" ).html() );
        			} , 200);
				}
			}
		}
	} );

	
	//Modal
	function get_form_hook( $form )
	{
		return ( $form.data( 'hook' ) && typeof( $form.data( 'hook' ) ) !== 'undefined' )? $form.data( 'hook' ) : 'general';
	}
	$( document ).on( 'click', '.toggle-modal', function()
	{
		var elem = $( this );
		var tpl = $( this ).data( 'tpl' );
		var actions = $( this ).data( 'actions' );
		var id = ( typeof( $( this ).data( 'id' ) ) !== 'undefined' )? $( this ).data( 'id' ) : 0;
		var action = ( typeof( $( this ).data( 'action' ) ) !== 'undefined' )? $( this ).data( 'action' ) : "";
		var service = ( typeof( $( this ).data( 'service' ) ) !== 'undefined' )? $( this ).data( 'service' ) : "";
		var title = ( typeof( $( this ).data( 'title' ) ) !== 'undefined' )? $( this ).data( 'title' ) : "";
		var message = ( typeof( $( this ).data( 'message' ) ) !== 'undefined' )? $( this ).data( 'message' ) : "";

		var modalElem = $( '#' + $( this ).data( 'modal' ) );
		modalElem.find( '.modal-title' ).html( title );
		modalElem.find( '.confirm-message' ).html( message );

		var content = $( '#' + tpl + 'TPL' ).html();
		if( typeof( content ) !== 'undefined' )
		{
			content = content.replace( "{id}", id );
			content = content.replace( "{service}", service );
			content = content.replace( "{action}", action );

			modalElem.find( '.modal-body' ).html( content );
		}

		if( modalElem.hasClass( 'modalForm' ) && modalElem.find( '.modal-body form' ).length && modalElem.find( '.modal-body form' ).hasClass( 'new' ) )
	 	{
	 		var actionHook = get_form_hook( modalElem.find( '.modal-body form' ) );
	 		if( typeof( formCache ) !== 'undefined' && typeof( formCache[actionHook] ) !== 'undefined' && formCache[actionHook] )
			{
				//modalElem.find( '.modal-body' ).html( formCache[actionHook] );
			}
	 	}

		var action = [];
		if( modalElem.hasClass( 'modalForm' ) ) action = [ 'close', 'submit' ];
		if( modalElem.hasClass( 'modalConfirm' ) ) action = [ 'no', 'yes' ];
		if( modalElem.hasClass( 'modalView' ) ) action = [ 'close' ];
		if( modalElem.hasClass( 'modalPrint' ) ) action = [ 'close' ];

		if( actions && typeof( actions ) !== 'undefined' )
			action = actions.split( '|' );
		
		$.each( action, function ( key, value )
		{
			var btn = modalElem.find( '.footer-action-'+value ).html();
			modalElem.find( '.modal-footer' ).append( btn );
		} );

		//open modal
		modalElem.modal( { show:true } );

		$( document ).trigger( 'onModalFormLoaded', [ modalElem, elem ] );
		$( document ).trigger( 'onDynamicElement' );

		return false;
	} );
	$( document ).on( 'hidden.bs.modal', '.modal', function( e ) 
	{
		if( $( '.modal:visible' ).length ) $( 'body' ).addClass('modal-open');

	 	var modalElem = $( this );

	 	if( modalElem.hasClass( 'modalForm' ) && modalElem.find( '.modal-body form' ).length && modalElem.find( '.modal-body form' ).hasClass( 'new' ) )
	 	{
	 		if( ! modalElem.find( '.modal-body form' ).hasClass( 'done' ) )
	 		{
	 			var actionHook = get_form_hook( modalElem.find( '.modal-body form' ) );
	 			formCache[actionHook] = modalElem.find( '.modal-body form' ).clone();
	 		}
	 		else
	 		{
	 			formCache[actionHook] = null;
				delete formCache[actionHook];
	 		}
	 	}

	 	modalElem.find( '.modal-body' ).html( '' );
		modalElem.find( '.modal-title' ).html( '' );
		modalElem.find( '.modal-footer' ).html( '' );
	} );
	$( document ).on( 'show.bs.modal', '.modal', function (event) 
	{
        var zIndex = 1040 + ( 10 * $('.modal:visible').length );
        $(this).css('z-index', zIndex);
        setTimeout(function() 
        {
            $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
        }, 0);
    });
	var modalConfirm = function( callback, elem, params )
	{
		var modalElem = $( '#' + params.modal );
		modalElem.find( '.modal-title' ).html( params.title );
		modalElem.find( '.confirm-message' ).html( params.message );

		var content = $( '#' + params.tpl + 'TPL' ).html();
		if( typeof( content ) !== 'undefined' )
		{
			content = content.replace( "{id}", params.id );
			content = content.replace( "{service}", params.service );
			content = content.replace( "{action}", params.action );

			modalElem.find( '.modal-body' ).html( content );
		}

		$.each( params.actionBtn, function ( key, value )
		{
			var btn = modalElem.find( '.footer-action-'+value ).html();
			modalElem.find( '.modal-footer' ).append( btn );
		} );
		
	    modalElem.modal( { show:true } );

		modalElem.find( "button.action-yes" ).on( "click", function()
		{
	    	callback( true );
	  	});
	  
	  	modalElem.find( "button.action-no" ).on( "click", function()
	  	{
	    	callback(false);
	  	});
	};
	$( document ).on( 'click', '.modal button.action-submit, .modal button.action-import', function()
	{//here
		if( $( this ).closest( '.modal' ).find( '.sortable_row' ).length )
		{
			var c = 1;
			$( this ).closest( '.modal' ).find( '.sortable_row .sortable_item_number' ).each(function( index ) {
			  	$( this ).val( c ); c++;
			});
		}
		$( this ).closest( '.modal' ).find( 'form' ).submit();
	} );
	$( document ).bind( 'onModalFormLoaded', function( e, modalElem )
	{
		var elem = ( typeof( modalElem ) === 'undefined' )? $( 'from.needValidate' ) : modalElem.find( 'form.needValidate' );
		
		elem.validate(
		{
			//focusCleanup: true,
			//showErrors: function( errorMap, errorList ) {},
			//ignore: ".ignore",
		 	submitHandler: function( element ) 
		 	{
		    	general_form_handler( element );
		  	},
		  	invalidHandler: function( event, validator ) 
		  	{},
			highlight: function( element, errorClass ) 
			{
			    $( element ).closest( '.form-group' ).addClass( 'has-error' );
			},
			unhighlight: function( element, errorClass, validClass ) 
			{
		    	$( element ).closest( '.form-group' ).removeClass( 'has-error' );
		  	},
		  	errorPlacement: function( error, element ) 
		  	{
            	//Empty to NOT show error
      		},
		});
	} );

	//Export
	$( document ).on( 'click', '.modal button.action-export', function()
	{
		var modalElem = $( this ).closest( '.modal' );
		var $form = modalElem.find( 'form' );
		
		var actionHook = get_form_hook( modalElem.find( '.modal-body form' ) );
		var formData = $form.serializeArray();
		var param = {
			'action'	: wcwh.appid+'_'+actionHook,
			'form' 		: $.param( formData ),
			'token' 	: $form.data( 'token' ),
			'wh'		: $( '.wcwh-section' ).data( 'wh' ),
			'agent'		: JSON.stringify( getAgent() ),
			'section'	: $( '.listing-form' ).data( 'section' ),
		};
		
		//window.location.href = window.location.href.split('?')[0]+"/?"+$.param( param );

		var form = document.createElement('FORM');
		form.method = 'POST';
		form.action = window.location.origin + window.location.pathname;
		form.target = '_self';

		$.each( param, function ( key, value )
		{
			var element = document.createElement("input");
	        element.type = "hidden";
	        element.value = value;
	        element.name = key;
	        form.appendChild(element);
		});
		document.body.appendChild(form);
		form.submit(); 
		document.body.removeChild(form);

		return false;
	} );

	//Export Print
	$( document ).on( 'click', '.modal button.action-printing', function()
	{
		var modalElem = $( this ).closest( '.modal' );
		var $form = modalElem.find( 'form' );
		
		var actionHook = get_form_hook( modalElem.find( '.modal-body form' ) );
		var formData = $form.serializeArray();
		var param = {
			'action'	: wcwh.appid+'_'+actionHook,
			'form' 		: $.param( formData ),
			'token' 	: $form.data( 'token' ),
			'wh'		: $( '.wcwh-section' ).data( 'wh' ),
			'agent'		: JSON.stringify( getAgent() ),
			'section'	: $( '.listing-form' ).data( 'section' ),
		};
		
		//window.open( window.location.href.split('?')[0]+"/?"+$.param( param ), '_blank');

		var form = document.createElement('FORM');
		form.method = 'POST';
		form.action = window.location.origin + window.location.pathname;
		form.target = '_blank';

		$.each( param, function ( key, value )
		{
			var element = document.createElement("input");
	        element.type = "hidden";
	        element.value = value;
	        element.name = key;
	        form.appendChild(element);
		});
		document.body.appendChild(form);
		form.submit(); 
		document.body.removeChild(form);

		return false;
	} );

	//Listing
	$( document ).on( 'submit', '.listing-form, .modal form', function()
	{
		return false;	//prevent default form submission
	} );
	$( document ).on( 'click', '#search-submit', function()
	{
		var statKey = 'status';
		var k = $( '.tablenav .statuses' ).data( 'key' );
		if( typeof k !== 'undefined' && k ) statKey = k;
		
		general_listing_handler( $( this ), $( this ).closest( '.listing-form' ), $( '.tablenav .statuses a.active:first' ).data( statKey ) );

		return false;
	} );
	$( document ).on( 'click', '.tablenav .statuses a', function()
	{
		var statKey = 'status';
		var k = $( '.tablenav .statuses' ).data( 'key' );
		if( typeof k !== 'undefined' && k ) statKey = k;

		//$( '#orderby-input' ).val( '' );
		//$( '#order-input' ).val( '' );
		//$( '#current-page-selector' ).val( 1 );
		
		if( ! $( this ).hasClass( 'active' ) )
			general_listing_handler( $( this ), $( this ).closest( '.listing-form' ), $( this ).data( statKey ) );

		return false;
	} );
	$( document ).on( 'click', '.pagination-links a.pg-btn', function()
	{
		var statKey = 'status';
		var k = $( '.tablenav .statuses' ).data( 'key' );
		if( typeof k !== 'undefined' && k ) statKey = k;
		
		$( '#current-page-selector' ).val( $( this ).data( 'page' ) );
		general_listing_handler( $( this ), $( this ).closest( '.listing-form' ), $( '.tablenav .statuses a.active' ).data( statKey ) );

		return false;
	} );
	$( document ).on( 'click', '.sortable-col', function()
	{
		var statKey = 'status';
		var k = $( '.tablenav .statuses' ).data( 'key' );
		if( typeof k !== 'undefined' && k ) statKey = k;
		
		$( '#orderby-input' ).val( $( this ).data( 'orderby' ) );
		$( '#order-input' ).val( $( this ).data( 'order' ) );
		general_listing_handler( $( this ), $( this ).closest( '.listing-form' ), $( '.tablenav .statuses a.active' ).data( statKey ) );

		return false;
	} );
	$( document ).on( 'click', '.sortable-reset', function()
	{
		var statKey = 'status';
		var k = $( '.tablenav .statuses' ).data( 'key' );
		if( typeof k !== 'undefined' && k ) statKey = k;
		
		$( '#orderby-input' ).val( '' );
		$( '#order-input' ).val( '' );
		general_listing_handler( $( this ), $( this ).closest( '.listing-form' ), $( '.tablenav .statuses a.active' ).data( statKey ) );

		return false;
	} );

	//Link Action
	$( document ).on( 'click', '.linkAction', function()
	{
		var elem = $( this ); //action btn
		var elem_id = ( typeof( elem.attr('id') ) !== 'undefined' )? elem.attr('id') : "";
		var secOpt = $('[name="action"]').find(":selected").text().toLowerCase();
		var id = ( typeof( elem.data( 'id' ) ) !== 'undefined' )? elem.data( 'id' ) : 0;
		var service = ( typeof( elem.data( 'service' ) ) !== 'undefined' )? elem.data( 'service' ) : "";
		var action = ( typeof( elem.data( 'action' ) ) !== 'undefined' )? elem.data( 'action' ) : "";

		var modal 	= ( typeof( elem.data( 'modal' ) ) !== 'undefined' )? elem.data( 'modal' ) : "";
		var tpl 	= ( typeof( elem.data( 'tpl' ) ) !== 'undefined' )? elem.data( 'tpl' ) : "";
		var actions = ( typeof( elem.data( 'actions' ) ) !== 'undefined' )? elem.data( 'actions' ) : "";
		var title 	= ( typeof( $( this ).data( 'title' ) ) !== 'undefined' )? $( this ).data( 'title' ) : "";
		var message = ( typeof( $( this ).data( 'message' ) ) !== 'undefined' )? $( this ).data( 'message' ) : "";
		var form 	= ( typeof( $( this ).data( 'form' ) ) !== 'undefined' )? $( this ).data( 'form' ) : "";
		var source	= ( typeof( $( this ).data( 'source' ) ) !== 'undefined' )? $( this ).data( 'source' ) : "";
		var strict	= ( typeof( $( this ).data( 'strict' ) ) !== 'undefined' && $( this ).data( 'strict' ).length > 0 )? true : false;
		var href = ( typeof( elem.data( 'href' ) ) !== 'undefined' )? elem.data( 'href' ) : "";

		var actionBtn = [ 'no', 'yes' ];
		if( actions.length ) actionBtn = actions.split( '|' );
		var param = { 
			id: id, service: service, action: action, 
			modal: modal, tpl: tpl, actionBtn: actionBtn, title: title, message: message, 
			form: form, confirmForm : '', source: source, strict: strict,
			href: href,
		};

		if( service.length )
		{
			if( modal.length && $( '#' + modal ).hasClass( 'modalConfirm' ) && secOpt.indexOf('print') == -1 )
			{
				modalConfirm( function( confirm )
					{
						if( confirm )
						{
							param.confirmed = true;
							if( $( '#' + modal ).find( 'form' ).length )
								param.confirmForm = $( '#' + modal ).find( 'form' ).attr( 'id' );
							general_link_handler( elem, param );
						}
					},
					elem,
					param
				);
			}
			else if( modal.length && $( '#' + modal ).hasClass( 'modalImEx' ) || 
				( ( elem_id == 'doaction' || elem_id == 'doaction2' ) && secOpt.indexOf('print') != -1 ) )
			{
				var statKey = 'status';
				var k = $( '.tablenav .statuses' ).data( 'key' );
				if( typeof k !== 'undefined' && k ) statKey = k;

				var $list = $( '.listing-form' );
				var listData = $list.serializeArray();
				listData.push( { name: "filter["+statKey+"]", value: ( ( status !== '' )? status : $( '.tablenav .statuses a.active:first' ).data( statKey ) ) } );

				var param = {
					'action'	: wcwh.appid+'_'+ elem.data( 'section' )  + '_submission',
					'form' 		: $.param( listData ),
					'token' 	: $list.data( 'token' ),
					'comp'		: $( '.wcwh-section' ).data( 'comp' ),
					'wh'		: $( '.wcwh-section' ).data( 'wh' ),
					'tab'		: $( '.wcwh-section' ).data( 'tab' ),
					'agent'		: JSON.stringify( getAgent() ),
					'section'	: $( '.listing-form' ).data( 'section' ),
				};

				var form = document.createElement('FORM');
				form.method = 'POST';
				form.action = window.location.origin + window.location.pathname;
				form.target = '_blank';
		
				$.each( param, function ( key, value )
				{
					var element = document.createElement("input");
					element.type = "hidden";
					element.value = value;
					element.name = key;
					form.appendChild(element);
				});
				document.body.appendChild(form);
				form.submit(); 
				document.body.removeChild(form);
		
				return false;
			}
			else
				general_link_handler( elem, param );
		}

		return false;
	} );


	//dynamic element
	$( document ).on( 'click', '.dynamic-action', function()
	{
		var source = $( this ).data( 'source' );
		var tpl = $( this ).data( 'tpl' );
		var target = $( this ).data( 'target' );
		var limit = $( this ).data( 'limit' );
		var repeative = $( this ).data( 'repeative' );
		if( typeof( repeative ) === 'undefined' ) repeative = false;
		
		var selected = $( source ).val();
		if( selected )
		{	
			var values = [];
			if( Array.isArray( selected ) ) values = selected;
			else values.push( selected );

			$.each( values, function ( key, value )
			{
				if( value == '' ) return true;

				var $selected = $( source ).find( 'option[value="'+value+'"]' );
				var data = $selected.data();
				var add = true;

				var max = 0;
				$( target ).children( 'tr' ).each(function( index ) {
				 	var seq = $( this ).data( 'seq' );
				 	if( seq > max ) max = seq;
				 	
				 	if( ! repeative && data.id == $( this ).data( 'id' ) ) add = false;
				});
				data.i = max + 1;

				if( ! add ) return true;
				if( typeof limit != 'undefined' && limit > 0 && max >= limit ) return true;

				var content = $( '#' + tpl ).html();
				if( typeof( content ) !== 'undefined' && typeof( data ) !== 'undefined' )
				{
					$.each( data, function ( key, value )
					{
						var regex = new RegExp( '{'+key+'}', 'g' );
						content = content.replace( regex, value );
					} );

					$( target ).append( content );
				}
			} );

			$( source ).val(null).trigger('change');
			$( document ).trigger( 'onDynamicElement' );
		}
	} );
	$( document ).on( 'click', '.dynamic-element', function()
	{
		var tpl = $( this ).data( 'tpl' );
		var target = $( this ).data( 'target' );
		var child = $( this ).data( 'children' );
		child = ( typeof child !== 'undefined' )? child : 'tr';

		var data = $( this ).data();
		var content = $( '#' + tpl ).html();

		var max = 0;
		$( target ).children( child ).each(function( index ) {
		 	var seq = $( this ).data( 'seq' );
		 	if( seq > max ) max = seq;
		});
		data.i = max + 1;
		
		if( typeof( content ) !== 'undefined' && typeof( data ) !== 'undefined' )
		{
			$.each( data, function ( key, value )
			{
				var regex = new RegExp( '{'+key+'}', 'g' );
				content = content.replace( regex, value );
			} );

			if( typeof( data.addafter ) !== 'undefined' && data.addafter.length )
				$( this ).closest( data.addafter ).after( content );
			else
				$( target ).append( content );
		}

		$( document ).trigger( 'onDynamicElement' );
	} );
	$( document ).on( 'click', '.remove-row', function()
	{
		if( typeof( $( this ).data( 'remove' ) ) !== 'undefined' && typeof( $( this ).data( 'target' ) ) == 'undefined' )
		{
			$( this ).closest( $( this ).data( 'remove' ) ).remove();
		}
		else if( typeof( $( this ).data( 'remove' ) ) !== 'undefined' && typeof( $( this ).data( 'target' ) ) !== 'undefined' )
		{
			var elem = $( this ).closest( $( this ).data( 'target' ) );
			elem.find( $( this ).data( 'remove' ) ).remove();
		}
		else
			$( this ).closest( 'tr' ).remove();
		
		listNum();
	} );


	//reprocess element
	$( document ).on( 'click', '.reprocess-action', function()
	{
		var source = $( this ).data( 'source' );
		var tpl = $( this ).data( 'tpl' );
		var target = $( this ).data( 'target' );
		var field = $( this ).data( 'field' );
		
		var selected = $( source ).val();
		if( selected )
		{	
			var values = [];
			if( Array.isArray( selected ) ) values = selected;
			else values.push( selected );

			$.each( values, function ( key, value )
			{
				if( value == '' ) return true;

				var ids = value.split( ',' );
				$.each( ids, function ( k, val )
				{
					var $selected = $( field ).find( 'option[value="'+val+'"]' );
					var data = $selected.data();
					var add = true;

					var max = 0;
					$( target ).children( 'tr' ).each(function( index ) {
					 	var seq = $( this ).data( 'seq' );
					 	if( seq > max ) max = seq;

					 	if( data.id == $( this ).data( 'id' ) ) add = false;
					});
					data.i = max + 1;

					if( ! add ) return true;

					var content = $( '#' + tpl ).html();
					if( typeof( content ) !== 'undefined' && typeof( data ) !== 'undefined' )
					{
						$.each( data, function ( key, value )
						{
							var regex = new RegExp( '{'+key+'}', 'g' );
							content = content.replace( regex, value );
						} );

						$( target ).append( content );
					}
				} );
			} );

			$( source ).val(null).trigger('change');
			$( document ).trigger( 'onDynamicElement' );
		}
	} );
	

	/**
	 *	Ajax Submission
	 *	---------------------------------------------------------------------------------------------------
	 */
	var ajaxSession = [];

	function getAgent()
	{
		var agent = detect.parse( navigator.userAgent );
		if( typeof( agent ) !== 'undefined' )
		{
			return { browser: agent.browser.name, device: agent.device.type, os: agent.os.name };
		}
	}

	function general_link_handler( elem, params )
	{
		if( typeof( elem ) === 'undefined' || typeof( params ) === 'undefined' ) return false;

		var actionHook = ( params.service )? params.service : 'default';
		var $form = elem.closest( ".listing-form" );
		if( ! $form.length ) $form = elem.closest( ".wcwh-section" ).find( ".listing-form" );

		var formData = { id: params.id, action: params.action };
		var fields = ( params.form.length )? $( "#" + params.form ).serializeArray() : [];
		formData.listing_form = $.param( fields );

		var remarks = ( params.confirmForm.length )? $( "#" + params.confirmForm ).serializeArray() : [];
		formData.info = $.param( remarks );

		if( params.source.length )
		{
			formData.id = $( params.source ).val();
		}

		if( params.strict && !formData.id )
		{
			return false;
		}

		var statKey = 'status';
		var k = $( '.tablenav .statuses' ).data( 'key' );
		if( typeof k !== 'undefined' && k ) statKey = k;
		
		var $list = $( '.listing-form' );
		var listData = $list.serializeArray();
		listData.push( { name: "filter["+statKey+"]", value: ( ( status !== '' )? status : $( '.tablenav .statuses a.active:first' ).data( statKey ) ) } );

		var param = {
			'action' 	: wcwh.appid+'_'+actionHook,
			'form'		: formData,
			'listing'	: $.param( listData ),
			'token'		: $form.data( 'token' ),
			'wh'		: $( '.wcwh-section' ).data( 'wh' ),
			'tab'		: $( '.wcwh-section' ).data( 'tab' ),
			'ref_doc_type' : $( '.wcwh-section' ).data( 'ref_doc_type' ),
			'ref_issue_type' : $( '.wcwh-section' ).data( 'ref_issue_type' ),
			'diff_seller' : $( '.wcwh-section' ).data( 'diff_seller' ),
			'agent'		: getAgent(),
			'section'	: $form.data( 'section' ),
		};

		var $block = $form.closest( '.wcwh-section' );
		blocking( $block );
		
		var $blockModal;
		if( params.modal.length )
			$blockModal = $( '#' + params.modal ).find( '.modal-content' );
		if( params.confirmed && $( '#' + params.modal ).is( ':visible' ) )
			blocking( $blockModal );

		$.ajax({
			type:    'POST',
			url:     wcwh.ajax_url,
			data:    param,
			beforeSend: function()
			{
			},
			success: function( outcomes ) 
			{
				unblocking( $block );

				if ( outcomes ) {

					var outcome = $.parseJSON( outcomes );

					if( outcome.refresh ) location.reload();
					if( outcome.succ ) 
					{
					}
					

					if( params.confirmed && $( '#' + params.modal ).is( ':visible' ) )
					{
						$( '#' + params.modal ).modal( 'hide' );
						unblocking( $blockModal );
					}

					if( outcome.messages )
					{
						$( '.notice-container' ).append( outcome.messages );
					}

					if( outcome.content )
					{
						$.each( outcome.content, function ( key, value )
						{
							if( key == '.modal-body' )
							{
								if( params.modal.length )
									$( '#'+params.modal+' '+key ).html( value );
							}
							else
							{
								$( key ).html( value );
							}
						} );
					}

					if( outcome.segments )
					{
						$.each( outcome.segments, function ( key, value )
						{
							$( key ).replaceWith( value );
						} );
					}
					
					if( outcome.modal )
					{
						if( outcome.modal.title ) params.title = outcome.modal.title;
						if( outcome.modal.message ) params.message = outcome.modal.message;
						if( outcome.modal.actionBtn ) params.actionBtn = outcome.modal.actionBtn;
					}

					var modalElem;
					if( params.modal.length && ! params.confirmed )
					{	
						modalElem = $( '#' + params.modal );
						modalElem.find( '.modal-title' ).html( params.title );
						modalElem.find( '.confirm-message' ).html( params.message );

						$.each( params.actionBtn, function ( key, value )
						{
							var btn = modalElem.find( '.footer-action-'+value ).html();
							modalElem.find( '.modal-footer' ).append( btn );

							var attr = $( '.action-'+value ).attr('href');
							if( typeof( attr ) !== 'undefined' && params.href.length ) 
							{
							   $( '.action-'+value ).attr( 'href', params.href );
							}
						} );

						//open modal
						modalElem.modal( { show:true } );

						$( document ).trigger( 'onModalFormLoaded', [ modalElem ] );
					}
					else if( outcome.modal && outcome.modal.modal && params.confirmed )
					{
						var modalContent = $( '#'+params.modal+' .modal-body' ).html();
						if( outcome.modal.title ) params.title = outcome.modal.title;
						if( outcome.modal.actionBtn ) params.actionBtn = outcome.modal.actionBtn;
						if( outcome.modal.modal ) params.modal = outcome.modal.modal;

						if( params.modal.length )
						{
							modalElem = $( '#' + params.modal );
							modalElem.find( '.modal-title' ).html( params.title );
							if(modalContent)modalElem.find( '.modal-body').html(modalContent);

							$.each( params.actionBtn, function ( key, value )
							{
								var btn = modalElem.find( '.footer-action-'+value ).html();
								modalElem.find( '.modal-footer' ).append( btn );
							} );

							//open modal
							modalElem.modal( { show:true } );

							$( document ).trigger( 'onModalFormLoaded', [ modalElem ] );
						}
					}

					$( document ).trigger( 'onDynamicElement' );
				}
			},
			error: function( e, code, msg )
			{
				unblocking( $block );

				if( wcwh.debug ) console.log( code + ': ' + msg );
			},
			complete: function( e, stat )
			{
				$( document ).trigger( 'onAjax_'+actionHook+stat );

				unblocking( $block );

				if( wcwh.debug )
				{
					console.log( stat ); console.log( param );
				} 
			},
		})
		.always(function (response) 
		{
            unblocking( $block );
        });
	}

	function general_listing_handler( elem, $form, status = 'all' )
	{
		var actionHook = get_form_hook( $form );
		
		var statKey = 'status';
		var k = $( '.tablenav .statuses' ).data( 'key' );
		if( typeof k !== 'undefined' && k ) statKey = k;

		var formData = $form.serializeArray();
		formData.push( { name: "filter["+statKey+"]", value: ( ( status !== '' )? status : $( '.tablenav .statuses a.active:first' ).data( statKey ) ) } );

		var param = {
			'action' 	: wcwh.appid+'_'+actionHook,
			'form' 		: $.param( formData ),
			'token'		: $form.data( 'token' ),
			'wh'		: $( '.wcwh-section' ).data( 'wh' ),
			'tab'		: $( '.wcwh-section' ).data( 'tab' ),
			'ref_doc_type' : $( '.wcwh-section' ).data( 'ref_doc_type' ),
			'ref_issue_type' : $( '.wcwh-section' ).data( 'ref_issue_type' ),
			'diff_seller' : $( '.wcwh-section' ).data( 'diff_seller' ),
			'agent'		: getAgent(),
			'section'	: $form.data( 'section' ),
		};

		var $block = $form.closest( '.wcwh-section' );
		blocking( $block );

		$.ajax({
			type:    'POST',
			url:     wcwh.ajax_url,
			data:    param,
			beforeSend: function()
			{
				if( typeof( ajaxSession ) !== 'undefined' && typeof( ajaxSession[actionHook] ) !== 'undefined' && ajaxSession[actionHook] )
					return false;

				ajaxSession[actionHook] = true;
			},
			success: function( outcomes ) 
			{
				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				if ( outcomes ) {
					var outcome = $.parseJSON( outcomes );

					if( outcome.refresh ) location.reload();
					
					if( outcome.succ ) 
					{
					}
					
					if( outcome.messages )
					{
						$( '.notice-container' ).append( outcome.messages );
					}

					if( outcome.segments )
					{
						$.each( outcome.segments, function ( key, value )
						{
							$( key ).replaceWith( value );
						} );
					}

					if( outcome.datas )
					{
						//console.log(outcome.datas.chart);
						if( typeof chart !== 'undefined' )chart.destroy();
						if( outcome.datas.chart )
							chart = showGraph( chart, outcome.datas.chart.chartData, outcome.datas.chart.chartType, outcome.datas.chart.chartArgs );
					}

					$(document).find('#chartType').trigger('change');
					$(document).find("input[name='filter[data_group]']").trigger('keyup');
					$( document ).trigger( 'onDynamicElement' );
				}
			},
			error: function( e, code, msg )
			{
				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				if( wcwh.debug ) console.log( code + ': ' + msg );
			},
			complete: function( e, stat )
			{
				$( document ).trigger( 'onAjax_'+actionHook+stat );

				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				if( wcwh.debug )
				{
					console.log( stat ); console.log( param );
				} 
			}
		})
		.always(function (response) 
		{
            unblocking( $block );
			ajaxSession[actionHook] = false;
			delete ajaxSession[actionHook];
        });
	}
	
	function general_form_handler( element )
	{
		var $form = $( element ); //form
		var actionHook = get_form_hook( $form );

		if( $form.hasClass( 'needValidate' ) )
		{
			$form.validate();
			if( ! $form.valid() ) return false;
		}

		var formData = $form.serializeArray();

		var statKey = 'status';
		var k = $( '.tablenav .statuses' ).data( 'key' );
		if( typeof k !== 'undefined' && k ) statKey = k;

		var $list = $( '.listing-form' );
		var listData = $list.serializeArray();
		listData.push( { name: "filter["+statKey+"]", value: ( ( status !== '' )? status : $( '.tablenav .statuses a.active:first' ).data( statKey ) ) } );

		var param = {
			'action'	: wcwh.appid+'_'+actionHook,
			'form' 		: $.param( formData ),
			'listing'	: $.param( listData ),
			'token' 	: $form.data( 'token' ),
			'wh'		: ( $( '.wcwh-section' ).data( 'wh' ) )? $( '.wcwh-section' ).data( 'wh' ) : '',
			'tab'		: ( $( '.wcwh-section' ).data( 'tab' ) )? $( '.wcwh-section' ).data( 'tab' ) : '',
			'ref_doc_type' : ( $( '.wcwh-section' ).data( 'ref_doc_type' ) )? $( '.wcwh-section' ).data( 'ref_doc_type' ) : '',
			'ref_issue_type' : ( $( '.wcwh-section' ).data( 'ref_issue_type' ) )? $( '.wcwh-section' ).data( 'ref_issue_type' ) : '',
			'diff_seller' : ( $( '.wcwh-section' ).data( 'diff_seller' ) )? $( '.wcwh-section' ).data( 'diff_seller' ) : '',
			'agent'		: JSON.stringify( getAgent() ),
			'section'	: $( '.listing-form' ).data( 'section' ),
		};

		var $block = $form.parents( '.modal-content' );
		if( ! $block ) $block = $( '.wcwh-section' );
		blocking( $block );

		if( $form.find( 'input[type=file]' ).length )
		{
			var Datas = new FormData();
			$.each( param, function( key, value ){
			    Datas.append( key, value );
			});

			$form.find( 'input[type=file]' ).each( function( index ) 
			{
				var felem = $( this );
				$.each( felem[index].files, function( idx, file ){
					Datas.append( felem.attr( 'name' )+'[]', file );
				} );
			});

			$.ajax({
				type:    'POST',
				url:     wcwh.ajax_url,
				data:    Datas,
				processData: false,
	   			contentType: false,
	   			cache: false,
				beforeSend: function()
				{
					if( typeof( ajaxSession ) !== 'undefined' && typeof( ajaxSession[actionHook] ) !== 'undefined' && ajaxSession[actionHook] )
						return false;

					ajaxSession[actionHook] = true;
				},
				success: function( outcomes ) 
				{
					unblocking( $block );
					ajaxSession[actionHook] = false;
					delete ajaxSession[actionHook];

					if ( outcomes ) {
						var outcome = $.parseJSON( outcomes );

						if( outcome.refresh ) location.reload();
						
						if( outcome.succ ) 
						{
							$form.addClass( 'done' );
							$form.closest( '.modal' ).modal( 'hide' );

							formCache[actionHook] = null;
							delete formCache[actionHook];
							formCache = [];
						}
						
						if( outcome.messages )
						{
							$( '.notice-container' ).append( outcome.messages );
						}

						if( outcome.segments )
						{
							$.each( outcome.segments, function ( key, value )
							{
								$( key ).replaceWith( value );
							} );
						}

						if( outcome.content )
						{
							$.each( outcome.content, function ( key, value )
							{
								if( key == '.modal-body' )
								{
									if( params.modal.length )
										$( '#'+params.modal+' '+key ).html( value );
								}
								else
								{
									$( key ).html( value );
								}
							} );
						}

						if( outcome.modal )
						{
							var params = [];
							if( outcome.modal.title ) params.title = outcome.modal.title;
							if( outcome.modal.actionBtn ) params.actionBtn = outcome.modal.actionBtn;
							if( outcome.modal.modal ) params.modal = outcome.modal.modal;

							var modalElem;
							if( params.modal.length )
							{
								modalElem = $( '#' + params.modal );
								modalElem.find( '.modal-title' ).html( params.title );

								$.each( params.actionBtn, function ( key, value )
								{
									var btn = modalElem.find( '.footer-action-'+value ).html();
									modalElem.find( '.modal-footer' ).append( btn );
								} );

								//open modal
								modalElem.modal( { show:true } );

								$( document ).trigger( 'onModalFormLoaded', [ modalElem ] );
							}
						}

						$( document ).trigger( 'onDynamicElement' );
					}
				},
				error: function( e, code, msg )
				{
					unblocking( $block );
					ajaxSession[actionHook] = false;
					delete ajaxSession[actionHook];

					if( wcwh.debug ) console.log( code + ': ' + msg );
				},
				complete: function( e, stat )
				{
					$( document ).trigger( 'onAjax_'+actionHook+stat );

					unblocking( $block );
					ajaxSession[actionHook] = false;
					delete ajaxSession[actionHook];

					if( wcwh.debug )
					{
						console.log( stat ); console.log( param );
					} 
				}
			})
			.always(function (response) 
			{
	            unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];
	        });
		}
		else
		{
			$.ajax({
				type:    'POST',
				url:     wcwh.ajax_url,
				data:    param,
				beforeSend: function()
				{
					if( typeof( ajaxSession ) !== 'undefined' && typeof( ajaxSession[actionHook] ) !== 'undefined' && ajaxSession[actionHook] )
						return false;

					ajaxSession[actionHook] = true;
				},
				success: function( outcomes ) 
				{
					unblocking( $block );
					ajaxSession[actionHook] = false;
					delete ajaxSession[actionHook];

					if ( outcomes ) {
						var outcome = $.parseJSON( outcomes );

						if( outcome.refresh ) location.reload();
						
						if( outcome.succ ) 
						{
							$form.addClass( 'done' );
							$form.closest( '.modal' ).modal( 'hide' );

							formCache[actionHook] = null;
							delete formCache[actionHook];
							formCache = [];
						}
						
						if( outcome.messages )
						{
							$( '.notice-container' ).append( outcome.messages );
						}

						if( outcome.segments )
						{
							$.each( outcome.segments, function ( key, value )
							{
								$( key ).replaceWith( value );
							} );
						}

						if( outcome.content )
						{
							$.each( outcome.content, function ( key, value )
							{
								if( key == '.modal-body' )
								{
									if( params.modal.length )
										$( '#'+params.modal+' '+key ).html( value );
								}
								else
								{
									$( key ).html( value );
								}
							} );
						}

						if( outcome.modal )
						{
							var params = [];
							if( outcome.modal.title ) params.title = outcome.modal.title;
							if( outcome.modal.actionBtn ) params.actionBtn = outcome.modal.actionBtn;
							if( outcome.modal.modal ) params.modal = outcome.modal.modal;

							var modalElem;
							if( params.modal.length )
							{
								modalElem = $( '#' + params.modal );
								modalElem.find( '.modal-title' ).html( params.title );

								$.each( params.actionBtn, function ( key, value )
								{
									var btn = modalElem.find( '.footer-action-'+value ).html();
									modalElem.find( '.modal-footer' ).append( btn );
								} );

								//open modal
								modalElem.modal( { show:true } );

								$( document ).trigger( 'onModalFormLoaded', [ modalElem ] );
							}
						}

						$( document ).trigger( 'onDynamicElement' );
					}
				},
				error: function( e, code, msg )
				{
					unblocking( $block );
					ajaxSession[actionHook] = false;
					delete ajaxSession[actionHook];

					if( wcwh.debug ) console.log( code + ': ' + msg );
				},
				complete: function( e, stat )
				{
					$( document ).trigger( 'onAjax_'+actionHook+stat );

					unblocking( $block );
					ajaxSession[actionHook] = false;
					delete ajaxSession[actionHook];

					if( wcwh.debug )
					{
						console.log( stat ); console.log( param );
					} 
				}
			})
			.always(function (response) 
			{
	            unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];
	        });
		}
		
		return false;
	}

	//Heartbeat ReAuth
	$( document ).on( 'heartbeat-tick.wp-auth-check', function( e, data ) {
		if ( 'wp-auth-check' in data ) 
		{
			if ( ! data['wp-auth-check'] ) 
			{
				var form = $('#wp-auth-check-form'),
					frame, blockDiv;

				if ( form.length ) 
				{
					blockDiv = $( '.wcwh-section' );
					frame = $('#wp-auth-check-frame');
					frame.load( function() 
					{	
						var body = $(this).contents().find('body');
						if ( body && body.hasClass('interim-login-success') )
						{
							blocking( blockDiv );
							location.reload();
						}
					});
				}
			} 
		}
	});

	function showGraph( chart, chartData=[], chartType='', chartArgs=[] )
	{
		if( !chartData || !chartType ) return undefined;

		var graphTarget = $("#graphCanvas");

		var tooltip_format;
		var color_codes = [];

		//default chart options
		var chartOptions = {
			indexAxis: 'x',
			interaction: {
				intersect: false,
				axis: 'xy',
				mode: 'point'
			},
			maintainAspectRatio: true,
			responsive: true,
			plugins: {
				legend: {
					labels: {

					},

					position: 'top'
				},

				tooltip: {
					callbacks: {

					}
				},

				title: {
					display: false,
					text: '',
					font: {
						size: 20,
					}
				}
			},
			scales: {
				x: {
					position: 'bottom',
					display: true,
					title: {
						display: true,
						text: '',
						font: {
							size: 14,
						}
					},
					ticks: {
						font: {
							size: 12,
						}
					}
				},
				y: {
					display: true,
					title: {
						display: true,
						text: '',
						font: {
							size: 14,
						}
					},
					beginAtZero: true,
					ticks: {
						font: {
							size: 12
						},
						callback: function (value, index, values) { return value; }

					}
				}
			}
		};

		//console.log(chartOptions);
		//Check and set the chart args passed in
		if (typeof chartArgs['options'] !== 'undefined') {
			if (typeof chartArgs['options']['plugin-legend-pos'] !== 'undefined')
				chartOptions.plugins.legend.position = chartArgs['options']['plugin-legend-pos'];
			if (typeof chartArgs['options']['plugin-title'] !== 'undefined') {
				chartOptions.plugins.title.display = chartArgs['options']['plugin-title'][0];
				chartOptions.plugins.title.text = chartArgs['options']['plugin-title'][1];
			}
			if (typeof chartArgs['options']['Unit'] !== 'undefined') {
				chartOptions.scales.y.ticks.callback = function (value, index, values) { return chartArgs['options']['Unit'] + value; };

			}
			if (typeof chartArgs['options']['legend_callback'] !== 'undefined' && chartArgs['options']['legend_callback'] != false) {
				chartOptions.plugins.legend.labels.generateLabels = function (chart) {
					const original = Chart.overrides.pie.plugins.legend.labels.generateLabels;
					const labelsOriginal = original.call(this, chart);
					//Build an array of colors used in the datasets of the chart
					let datasetColors = chart.data.datasets.map(function (e) {
						return e.backgroundColor;
					});
					datasetColors = datasetColors.flat();
					//console.log(labelsOriginal);
					// Modify the color and hide state of each label
					labelsOriginal.forEach(label => {
						if (chartArgs['count'] > 1) {
							// There are twice as many labels as there are datasets. This converts the label index into the corresponding dataset index
							label.datasetIndex = (label.index - label.index % chartArgs['count']) / chartArgs['count'];

							// The hidden state must match the dataset's hidden state
							label.hidden = !chart.isDatasetVisible(label.datasetIndex);

							// Change the color to match the dataset
							label.fillStyle = datasetColors[label.index];
						}
						else {
							// Change the color to match the dataset
							label.fillStyle = datasetColors[label.index];
						}

					});

					return labelsOriginal;
				}

				if (chartArgs['count'] > 1) {
					chartOptions.plugins.legend.onClick = function (mouseEvent, legendItem, legend) {
						// toggle the visibility of the dataset from what it currently is
						legend.chart.getDatasetMeta(
							legendItem.datasetIndex
						).hidden = legend.chart.isDatasetVisible(legendItem.datasetIndex);
						legend.chart.update();
					}

					// 25/11/2022 duplicated function
					// chartOptions.plugins.tooltip.callbacks.label = function (context) {
					// 	const labelIndex = (context.datasetIndex * chartArgs['count']) + context.dataIndex;
					// 	console.log(chartArgs['count']);
					// 	return context.chart.data.labels[labelIndex] + ': ' + context.formattedValue;
					// }

				}


			}
			if (typeof chartArgs['options']['BeginAtZero'] !== 'undefined') {
				chartOptions.scales.y.beginAtZero = chartArgs['options']['BeginAtZero'];
			}
			if (typeof chartArgs['options']['XValue'] !== 'undefined') {
				chartOptions.scales.x.title.text = chartArgs['options']['XValue'];
			}
			if (typeof chartArgs['options']['YValue'] !== 'undefined') {
				chartOptions.scales.y.title.text = chartArgs['options']['YValue'];
			}

			//---jeff
			if (typeof chartArgs['options']['x_axis_stacked'] !== 'undefined') {
				chartOptions.scales.x.stacked = chartArgs['options']['x_axis_stacked'];
			}
			//---jeff

			if (typeof chartArgs['options']['y_axis_stacked'] !== 'undefined') {
				chartOptions.scales.y.stacked = chartArgs['options']['y_axis_stacked'];
			}

			if (chartType == 'pie' || chartType == 'doughnut' ) {
				chartOptions.scales.x.display = false;
				chartOptions.scales.y.display = false;
				//delete chartOptions.plugins.tooltip;
			}
			if (typeof chartArgs['options']['tooltip'] !== 'undefined') {
				tooltip_format = chartArgs['options']['tooltip'];
			}
			if (typeof chartArgs['options']['indexAxis'] !== 'undefined') {
				chartOptions.indexAxis = chartArgs['options']['indexAxis'];
				chartOptions.scales.x.position = 'top';//Set the x axis to the top
				if (chartOptions.indexAxis == 'y') {
					delete chartOptions.scales.y.ticks.callback;
					chartOptions.maintainAspectRatio = false;
					//Increase the height of the canvas if there are many datas so that the horizontal bar won't look too small for user
					if (chartData.labels.length >= 20 && chartData.labels.length <= 50)
						$('#graphCanvas').css("height", 1200);
					else if (chartData.labels.length >= 51 && chartData.labels.length <= 100)
						$('#graphCanvas').css("height", 3600);
					else if (chartData.labels.length >= 101 && chartData.labels.length <= 150)
						$('#graphCanvas').css("height", 5400);
					else if (chartData.labels.length >= 151 && chartData.labels.length <= 200)
						$('#graphCanvas').css("height", 7600);
					else if (chartData.labels.length >= 201 && chartData.labels.length <= 250)
						$('#graphCanvas').css("height", 9800);
					else if (chartData.labels.length >= 251 && chartData.labels.length <= 300)
						$('#graphCanvas').css("height", 11000);
					else if (chartData.labels.length >= 301)
						$('#graphCanvas').css("height", 13000);
					else
						$('#graphCanvas').css("height", 600);
				}
			}
			if (typeof chartArgs['options']['interaction_mode'] !== 'undefined') {
				chartOptions.interaction.mode = chartArgs['options']['interaction_mode'];
			}

			if (chartType) {
				chartOptions.plugins.tooltip.callbacks.label = function (context) {
					if (chartType == 'line' || chartType == 'bar') {
						var label = context.dataset.label || '';

						var contextParsedValue;

						if (label) {
							label += ': ';
						}

						if (chartOptions.indexAxis == 'y')
							contextParsedValue = context.parsed.x;
						else if (chartOptions.indexAxis == 'x')
							contextParsedValue = context.parsed.y;
					}
					else {
						// 25/11/2022 var label = context.chart.data.labels[labelIndex] || '';
						const labelIndex = (context.datasetIndex * chartArgs['count']) + context.dataIndex;
						var label = context.chart.data.labels[labelIndex] || '';
						var contextParsedValue;

						if (label) {
							label += ': ';
						}
						contextParsedValue = context.parsed;
					}


					if (contextParsedValue !== null) {
						switch (tooltip_format) {
							case 'currency':
								label += new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR' }).format(contextParsedValue);
								break;
							case 'litre':
								label += new Intl.NumberFormat('ms-MY', { style: 'unit', unit: 'liter' }).format(contextParsedValue);
								break;
							case 'kilogram':
								label += new Intl.NumberFormat('ms-MY', { style: 'unit', unit: 'kilogram' }).format(contextParsedValue);
								break;
							case 'gram':
								label += new Intl.NumberFormat('ms-MY', { style: 'unit', unit: 'gram' }).format(contextParsedValue);
								break;
							case 'milliliter':
								label += new Intl.NumberFormat('ms-MY', { style: 'unit', unit: 'milliliter' }).format(contextParsedValue);
								break;
							default:
								label += Intl.NumberFormat('ms-MY').format(contextParsedValue) + ' ' + tooltip_format;
								break;
						}
					}
					return label;
				};
			}

		}

		chart = new Chart(graphTarget, {
			type: chartType,
			data: {
				labels: ['No Data'],
				datasets: [{
					label: "No Data",
					data: [0, 0],

				}]
			},
			options: chartOptions
		});



		if (typeof chartArgs['colors'] !== 'undefined')
			color_codes = chartArgs['colors'];

		/*var colors = randomColor({
			luminosity: chartArgs['colors']['luminosity'],
			count : chartArgs['colors']['count'],
			format : chartArgs['colors']['format'],
			alpha : chartArgs['colors']['alpha'],
			hue : chartArgs['colors']['hue']
		});*/

		const LineChartColors = color_codes;

		if (chartData.length != 0) {
			var newDataset = {};
			var xy = {};

			if (chartType == 'line' || chartType == 'bar') {
				chart.data.labels = [''];
				for (var i = 0; i < chartData.labels.length; i++)
					chart.data.labels.push(chartData.labels[i]);
				chart.data.labels.push('');
			}
			else
				chart.data.labels = chartData.labels;

			chart.data.datasets = [];
			var count = 0;
			for (var i = 0; i < chartData.datasets.length; i++) {
				newDataset = {};
				newDataset.data = [];
				//---jeff---//
				var temp_chartT = chartType;
				if (chartData.datasets[i].type) {
					temp_chartT = chartData.datasets[i].type;
					newDataset.type = chartData.datasets[i].type;

				}
				if (chartData.datasets[i].parsing) {
					for (let cdp = 0; cdp < chartData.datasets[i].parsing.length; cdp++) {
						newDataset.parsing = chartData.datasets[i].parsing[cdp];

					}
				}
				//---jeff---//

				if (temp_chartT == 'line' || temp_chartT == 'bar') newDataset.label = chartData.datasets[i].label;
				if (temp_chartT == 'pie' || temp_chartT == 'doughnut' ) newDataset.backgroundColor = [];
				for (var j = 0; j < chartData.datasets[i].data.length; j++) {

					if (temp_chartT == 'line' || temp_chartT == 'bar') {
						xy = {};
						xy.x = chartData.datasets[i].data[j].x;
						xy.y = chartData.datasets[i].data[j].y;
						newDataset.data.push(xy);
					}
					else if (temp_chartT == 'pie' || temp_chartT == 'doughnut') {
						newDataset.data.push(chartData.datasets[i].data[j]);
						newDataset.backgroundColor.push(LineChartColors[count]);

					}
					count++;
				}
				if (temp_chartT == 'line' || temp_chartT == 'bar') {
					newDataset.backgroundColor = LineChartColors[i];
					newDataset.borderColor = LineChartColors[i];
				}
				if (typeof chartArgs['dataType'] !== 'undefined') {
					if (Array.isArray(chartArgs['dataType'][0])) {
						for (var x = 0; x < chartArgs['dataType'][0].length; x++) {
							if (chartArgs['dataType'][0][x] == i) newDataset.type = chartArgs['dataType'][1];
						}
					}
					else {
						if (chartArgs['dataType'][0] == i) newDataset.type = chartArgs['dataType'][1];
					}
				}

				if (typeof chartArgs['stackGroup'] !== 'undefined' && temp_chartT != 'pie' && temp_chartT != 'doughnut' ) {
					if (Array.isArray(chartArgs['stackGroup'][0])) {
						for (var x = 0; x < chartArgs['stackGroup'][0].length; x++) {
							if (chartArgs['stackGroup'][0][x] == i) newDataset.stack = chartArgs['stackGroup'][1];
						}
					}
					else {
						if (chartArgs['stackGroup'][0] == i) newDataset.stack = chartArgs['stackGroup'][1];
					}
				}

				if (typeof chartArgs['lineTension'] !== 'undefined') newDataset.lineTension = chartArgs['lineTension'];
				if (typeof chartArgs['fill'] !== 'undefined') newDataset.fill = chartArgs['fill'];
				if (typeof chartArgs['stepped'] !== 'undefined') newDataset.stepped = chartArgs['stepped'];
				newDataset.borderWidth = 2;
				chart.data.datasets.push(newDataset);
				chart.update();
			}
		}
		//console.log(chart);
		return chart;
	}

    /*test();
    function test()
    {	console.log( Date.parse("2021-08-27") );
    console.log( Date.parse("08/27/2021") );
    	if(dateCheck("02/05/2013","02/09/2013","02/07/2013"))
		    alert("Availed");
		else
		    alert("Not Availed");
    }

    function dateCheck( from, to, check ) 
    {
	    var fDate,lDate,cDate;
	    fDate = Date.parse(from);
	    tDate = Date.parse(to);
	    cDate = Date.parse(check);

	    if( (cDate <= tDate && cDate >= fDate) ) 
	    {
	        return true;
	    }

	    return false;
	}*/

	//date picker
	$( document ).on( 'change', '.doc_date.picker.removable', function()
	{
		var elem = $(this);
		//var width = elem.width();
		if( ! elem.parent().find( '.date-dropper-empty' ).length )
			elem.after('<span class="date-dropper-empty">×</span>');
	});
	$( document ).on( 'click', '.date-dropper-empty', function()
	{
		var elem = $(this);
		elem.parent().find( '.doc_date.picker' ).val( '' );
		elem.remove();
	});

	pickDate();
	function pickDate() {
		$('.doc_date.picker').each(function(index) {
		  var elem = $(this);
		  if (elem.val().length > 0)
			elem.trigger('change');
		});
	  
		function replaceDateFormat(input) {
		  const replacements = {
			'Y': 'yyyy',
			'y': 'yyyy',
			'M': 'mm',
			'm': 'mm',
			'D': 'dd',
			'd': 'dd'
		  };
	  
		  const output = input.replace(/Y|y|M|m|D|d/g, match => replacements[match]).toLowerCase();
	  
		  return output;
		}

		// convert mm/dd/yyyy to default format
		function convertDateFormat(dateString, targetFormat) {
		  const dateParts = dateString.split('/');
		  const month = dateParts[0];
		  const day = dateParts[1];
		  const year = dateParts[2];
	  
		  const formattedDate = targetFormat
			.replace('mm', month)
			.replace('dd', day)
			.replace('yyyy', year);
	  
		  return formattedDate;
		}
	  
		$('input.doc_date.picker').each(function() {
			var startDate = $(this).attr('data-dd-min-date');
			var endDate = $(this).attr('data-dd-max-date');
			var defDate = $(this).attr('data-dd-default-date');
			var dformat = $(this).attr('data-dd-format');
			var viewMode = '';
			
			dformat = (dformat !== undefined) ? replaceDateFormat(dformat) : 'yyyy-mm-dd';
			
			switch(dformat){
				case 'yyyy-mm':
					viewMode = "months";
				break;
				case 'yyyy':
					viewMode = "years";
				break;
				default:
					viewMode = '';
				break;
			}
	  
			if (startDate !== undefined) {
				startDate = convertDateFormat(startDate, dformat);
			}
	  
			if (endDate !== undefined) {
				endDate = convertDateFormat(endDate, dformat);
			}
			
			if (defDate !== undefined) {
				defDate = convertDateFormat(defDate, 'dd/mm/yyyy');
			}
			
			$(this).attr('autocomplete', 'off');
			
			$(this).datepicker({
				format: dformat,
				autoclose: true,
				todayHighlight: false,
				immediateUpdates: true,
				orientation: 'auto',
				daysOfWeekHighlighted: "0,6",
				startDate: startDate,
				endDate: endDate,
				minViewMode: viewMode, 
				defaultDate: defDate,
		  	});

		});

		// // dateDropper
		// $('input.doc_date.picker').dateDropper({
		// 	largeOnly: true,
		// 	startFromMonday: false,
		// 	theme: 'dateTheme'
		// });

		// $('.datedropper.dateTheme').css({
		// 	'overflow-y' : 'auto',
		// 	'position' : 'relative'
		// });
	}
	  
	$( ".modal .modal-body" ).scroll(function() {
		$('input.doc_date.picker').datepicker('place');
	});


	//-----------------------------------jeff
	qrreader();
	function qrreader()
	{
		if( $("#reader").length && !$("button.qrcodescanner").length )
		{
			let config = {fps: 10,qrbox: {width: 500, height: 500},disableFlip:false, supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]};
			var html5QrcodeScanner = new Html5QrcodeScanner("reader", config);
			html5QrcodeScanner.render(onScanSuccess);
		}
	}

	function onScanSuccess(decodedText, decodedResult) {
		console.log( 'Decoded Data: '+ decodedText);

		barcode_handle( decodedText, $( "#reader" ), $( "#reader" ).closest( '.listing-form' ));
	}

	/*$(document).on('click', '.qrcodescanner', function ()
	{
		if($(this).attr('label') == 'display')
		{
			let config = {fps: 10,qrbox: {width: 500, height: 500}, disableFlip:false, supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]};
			var html5QrcodeScanner = new Html5QrcodeScanner("reader", config);
			html5QrcodeScanner.render(onScanSuccess);
			$(this).html('Dismiss');
			$(this).attr("label",'dismiss');
		}
		else
		{
			//html5QrcodeScanner.clear();
			$("#reader").empty();
			$(this).html('Scanner');
			$(this).attr("label",'display');
		}
	});*/

	$( document ).on( "click", ".close_scanned", function() 
	{
		if($(this).closest(".closable_div").length)
		{
			$(this).closest(".closable_div").remove();
		}
	        
	});

	function barcode_handle( term, elem, $form )
	{
		var actionHook = get_form_hook( $form );

		var formData = $form.serializeArray();
		formData.filter.qs = term;

		var param = {
			'action' 	: wcwh.appid+'_'+actionHook,
			'form' 		: $.param( formData ),
			'token'		: $form.data( 'token' ),
			'wh'		: $( '.wcwh-section' ).data( 'wh' ),
			'ref_doc_type' : $( '.wcwh-section' ).data( 'ref_doc_type' ),
			'ref_issue_type' : $( '.wcwh-section' ).data( 'ref_issue_type' ),
			'diff_seller' : $( '.wcwh-section' ).data( 'diff_seller' ),
			'agent'		: getAgent(),
			'section'	: $form.data( 'section' ),
		};

		var $block = $form.closest( '.wcwh-section' );
		blocking( $block );

		$.ajax({
			type:    'POST',
			url:     wcwh.ajax_url,
			data:    param,
			beforeSend: function()
			{
				if( typeof( ajaxSession ) !== 'undefined' && typeof( ajaxSession[actionHook] ) !== 'undefined' && ajaxSession[actionHook] )
					return false;

				ajaxSession[actionHook] = true;
			},
			success: function( outcomes ) 
			{
				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				if ( outcomes ) {
					var outcome = $.parseJSON( outcomes );

					

					if( outcome.content )
					{
						$.each( outcome.content, function ( key, value )
						{
							var sku = outcome.value;
							if( !$(".closable_div#view"+sku).length  )
							{
								var closable_div ='';
								closable_div += '<div class="container card closable_div p-2" id="view'+sku+'">';

								closable_div += '<div class="row p-2">';
								closable_div += '<div class="col-11">';
								closable_div +=  '<h2>SKU: '+sku+'</h2>';
								closable_div += '</div>';
								closable_div += '<div class="col-1">';
								closable_div += '<button type="button" class="btn close_scanned" aria-label="Close">x</button>';
								closable_div += '</div>';
								closable_div += '</div>';

								closable_div += '<div class="row">';
								closable_div += '<div class="appendoutcome col">';
								closable_div += '</div>';
								closable_div += '</div>';

								closable_div += '</div>';								

								if( $('.appendResult').length )
								{
									$('.appendResult').append(closable_div).find('#view'+sku+' .appendoutcome').html(value);
								}
							}
						} );
					}

					$( document ).trigger( 'onDynamicElement' );
				}
			},
			error: function( e, code, msg )
			{
				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				if( wcwh.debug ) console.log( code + ': ' + msg );
			},
			complete: function( e, stat )
			{
				$( document ).trigger( 'onAjax_'+actionHook+stat );

				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				if( wcwh.debug )
				{
					console.log( stat ); console.log( param );
				} 
			}
		});
	}

	function modalSelectTwoDestroy()
	{
		if( $('#wcwhModalForm input.select2').hasClass("select2-hidden-accessible")) 
		{   
		    $('#wcwhModalForm input.select2').select2('destroy');
		}
		if( $('#wcwhModalForm select.select2').hasClass("select2-hidden-accessible")) 
		{   
		    $('#wcwhModalForm select.select2').select2('destroy');
		}
	}

	$( document ).on( "change", ".dynamicFormUpdate", function()
	{
		var $elem = $( this );
		
		var source = ( typeof( $elem.data( 'source' ) ) !== 'undefined' )? $elem.data( 'source' ) : "";
		var $form = $elem.parents( 'form' );
		var $modal = ( typeof( $elem.data( 'modal' ) ) !== 'undefined' )? $( $elem.data( 'modal' ) ) : $elem.parents( '.modal.modalForm' );
		if( source.length )
		{
			var $form = $elem.parents( source );
		}

		var actionHook = get_form_hook( $form );
		var formData = $form.serializeArray();

		var param = {
			'action'	: wcwh.appid+'_'+actionHook+'_update',
			'form' 		: $.param( formData ),
			'listing'	: [],
			'token' 	: $form.data( 'token' ),
			'comp'		: $( '.wcwh-section' ).data( 'comp' ),
			'wh'		: $( '.wcwh-section' ).data( 'wh' ),
			'tab'		: $( '.wcwh-section' ).data( 'tab' ),
			'agent'		: JSON.stringify( getAgent() ),
			'section'	: $( '.listing-form' ).data( 'section' ),
		};

		var $block = $elem.parents( '.modal-content' );
		if( ! $block ) $block = $( '.wcwh-section' );
		blocking( $block );

		$.ajax({
			type:    'POST',
			url:     wcwh.ajax_url,
			data:    param,
			beforeSend: function()
			{
				if( typeof( ajaxSession ) !== 'undefined' && typeof( ajaxSession[actionHook] ) !== 'undefined' && ajaxSession[actionHook] )
					return false;

				ajaxSession[actionHook] = true;
			},
			success: function( outcomes ) 
			{
				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				modalSelectTwoDestroy();
				if ( outcomes ) 
				{
					var outcome = $.parseJSON( outcomes );
					
					if( outcome.messages )
					{
						$( '.notice-container' ).append( outcome.messages );
					}

					if( outcome.segments )
					{
						$.each( outcome.segments, function ( key, value )
						{
							$( key ).replaceWith( value );
						} );
					}

					if( outcome.content )
					{
						$.each( outcome.content, function ( key, value )
						{
							if( key == '.modal-body' )
							{
								if( $modal.length )
									$modal.find( key ).html( value );
							}
							else
							{
								$( key ).html( value );
							}
						} );
					}

					$( document ).trigger( 'onModalFormLoaded', [ $modal ] );

					$( document ).trigger( 'onDynamicElement' );
				}
			},
			error: function( e, code, msg )
			{
				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				if( wcwh.debug ) console.log( code + ': ' + msg );
			},
			complete: function( e, stat )
			{
				unblocking( $block );
				ajaxSession[actionHook] = false;
				delete ajaxSession[actionHook];

				if( wcwh.debug )
				{
					console.log( stat );
				} 
			}
		});
	} );

	//------------------jeff
});