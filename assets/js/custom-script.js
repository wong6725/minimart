jQuery(function($){

/*------------------------------------------
--------
--------REMITTANCE MONEY SERVICE - JEFF
--------
-------------------------------------------*/

        function currencyConvert (currencyD, amount)
        {
            if(!currencyD) return;

            let currString = new Intl.NumberFormat('en-US',{
                style: 'currency',
                currency: currencyD,
                currencyDisplay: 'narrowSymbol'
            }).format(amount);

            return currString;
        }

        $.fn.filterSelect2 = function(data, data2){
            if( $(this).is('select') )
            {
                var refselect = $(this).data('selectrefclass');
                var this_selector = $(this);

                if(refselect)
                {
                    if($('select.'+refselect).length)
                    {
                        var $clonedSelect = $('select.'+refselect).clone();
                        $(this).empty();
                        if(data)
                        {
                            var id = '';
                            if(refselect == 'schargeRef')
                            {
                                $('select.'+refselect).find('option').each(function(){
                                    var from = parseFloat($(this).data( 'from_amt' ));
                                    var to = parseFloat($(this).data( 'to_amt' ));
                                    var from_currency = $(this).data( 'from_currency' );
                                    var to_currency = $(this).data( 'to_currency' );
                                    data = parseFloat(data);
                                    if( 'MYR' == from_currency && data2 == to_currency && data >= from && data <= to)
                                    {
                                        id = $(this).data( 'scid' );
                                        this_selector.append( $(this).clone() );
                                    }
                                    else
                                    {
                                        if('MYR' == from_currency && to_currency == 'DEF' && data >= from && data <= to)
                                        {
                                            id = $(this).data( 'scid' );
                                            this_selector.append( $(this).clone() );
                                        }
                                    }
                                });
                                if(id) this_selector.find('[data-scid ="'+id+'"]').attr('selected','selected').change();
                                else this_selector.change();
                            }
                            else if(refselect == 'exc_intRef')
                            {
                                if(!data2) data2 = new Date();
                                else data2 = new Date(data2);
                                
                                let closest = Infinity;

                                $('select.'+refselect).find('option').each(function(){
                                    var to_currency = $(this).data( 'to_currency' );
                                    var from_currency =  $(this).data( 'from_currency' );
                                    let since =  new Date($(this).data( 'since' ));

                                    if(data == to_currency && from_currency == 'MYR' && since.getTime() >= data2.getTime() && since.getTime() <= data2.getTime() && since.getTime() < closest)
                                    {
                                        closest = since.getTime();
                                        id = $(this).data( 'exrid' );
                                        this_selector.append($(this).clone());
                                    }
                                });

                                if(id) 
                                {
                                    this_selector.find('[data-exrid ="'+id+'"]').attr('selected','selected').change();
                                }
                                else
                                {
                                    closest = 0;
                                    $('select.'+refselect).find('option').each(function(){
                                        var to_currency = $(this).data( 'to_currency' );
                                        var from_currency =  $(this).data( 'from_currency' );
                                        let since =  new Date($(this).data( 'since' ));
                                        if(data == to_currency && from_currency == 'MYR' && since.getTime() <= data2.getTime() && since.getTime() > closest)
                                        {
                                            closest = since.getTime();
                                            id = $(this).data( 'exrid' );
                                            this_selector.append($(this).clone());
                                        }
                                        
                                    });
                                }

                                if(id) this_selector.find('[data-exrid ="'+id+'"]').attr('selected','selected').change();
                                else this_selector.change();
                            }
                        }
                        else
                        {
                            $(this).append( $('option', $clonedSelect).clone() ).change();
                        }

                    }
                }
            }
        }

        //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
       $( document ).on( 'shown.bs.modal', '.modal', function (event) 
        {
            if($('.ref_exchange_rate').length)
            {
                let rer = $('.ref_exchange_rate').val();
                if(!rer)
                {
                    $('.ref_exchange_rate').val($('option:selected', '.exc_int').data('exrid'));
                }                
            }
            
            if($('.exchange_rate').length)
            {
                let er = $('.exchange_rate').val();
                if(!er)
                {
                    $('.exchange_rate').val($('select.exc_int :selected').data('rate'));  
                }
                else
                {
                    $('.exchange_rate').val(er);  
                }
            }                                 
        });
        
       
        $(document).on('click', '.dynamic-radio', function(){
            let source = $( this ).data( 'source' );
            let tpl = $( this ).data( 'tpl' );
            let target = $( this ).data( 'target' );

            let content = $( '#' + tpl ).html();
            let data = $( '#' + source ).data();
            let data_currency = $( '#' + source ).data('currency');
            let data_country = $( '#' + source ).data('bank_country');
            let date = $( '.exc_date' ).val();

            if( typeof( content ) !== 'undefined' && typeof( data ) !== 'undefined')
            {
                $.each( data, function ( key, value )
                {
                    let regex = new RegExp( '{'+key+'}', 'g' );
                    content = content.replace( regex, value );
                });
                $(target).html("");
                $(target ).append( content );

                $(target).find("[name='_form[bank_country]']").find("option[value='"+data_country+"']").attr('selected', 'selected');
                $(target).find("[name='_form[currency]']").find("option[value='"+data_currency+"']").attr('selected', 'selected');
                $(document ).trigger( 'onDynamicElement' );
            }

            $('select.exc_int').filterSelect2(data_currency,date);
        });

        $( document ).on( 'change', '.exc_date', function()
        { 
            let closestElem = $( this ).closest( '.modal-body' );
            let date = $(this).val();
            let data_currency = closestElem.find('.CurrencySelect').val();
            
            $('select.exc_int').filterSelect2(data_currency, date);           
        });

        $(document).on('change', '.CurrencySelect', function(){
            let closestElem = $( this ).closest( '.modal-body' );
            let date = closestElem.find('.exc_date').val();
            let data_currency = $(this).val();
            let base_int = closestElem.find('.base_int').val(); 
            $('select.exc_int').filterSelect2(data_currency,date);
            $('select.scharge').filterSelect2(base_int, data_currency);                

        });

        $( document ).on( 'keyup', '.base_int', function()
        {
            let closestElem = $( this ).closest( '.modal-body' );
            let target = $( this ).data('set_hidden');
            let currency = $( this ).data('currency');
            let val = parseFloat($( this ).val().trim());

            let sc_currency = closestElem.find('.CurrencySelect').val(); 

            let timer = 0;
            clearTimeout(timer);
            timer = setTimeout(function () {
                if(!isNaN(val))
                {
                    $(target).val(val);
                    let target_val = $(target).val();
                    $( this ).val(currencyConvert(currency,target_val));
                    $('select.scharge').filterSelect2(target_val,sc_currency);                
                }
                else
                {
                    $(target).val('');
                    let target_val = $(target).val();
                    $( this ).val('');
                    $('select.scharge').filterSelect2(target_val,sc_currency);
                }
            }, 700);
           
        });

        $( document ).on( 'change', '.exc_int', function()
        {
            let closestElem = $( this ).closest( '.modal-body' );
            let exrid = $('select.exc_int :selected').data('exrid');
            let exrrate = $('select.exc_int :selected').data('rate');

            if(!exrid) closestElem.find('.ref_exchange_rate').val('');
            else closestElem.find('.ref_exchange_rate').val(exrid);

            if(!exrrate) closestElem.find('.exchange_rate').val('');
            else closestElem.find('.exchange_rate').val(exrrate);
           
        });

        $( document ).on( 'change', '.scharge', function()
        {
            let closestElem = $( this ).closest( '.modal-body' );
            let sc_id = $('select.scharge :selected').data('scid');
            let sc_charge = $('select.scharge :selected').data('charge');

            if(!sc_id) closestElem.find('.service_charge_id').val('');
            else closestElem.find('.service_charge_id').val(sc_id);

            if(!sc_charge) closestElem.find('.service_charge').val('');
            else closestElem.find('.service_charge').val(sc_charge);
        });

        $( document ).on( 'change', '.Bcalculation', function()
        {
            var closestElem = $( this ).closest( '.modal-body' );
            var elem = $(this);

            var base = 0;
            var exc_r = 0;
            var sc = 0;
            var exc_rprice = 0;
            var sc_price = 0;
            var total = 0;

            let time = 0;
            clearTimeout(time);
            time = setTimeout(function(){
                if(closestElem)
                {
                    closestElem.find('.Bcalculation').each(function()
                    {
                        if($(this).hasClass('base_int'))
                        {
                            let target  = $(this).data('set_hidden');
                            base = Number($(target).val());
                        }
                        else if($(this).hasClass('exc_int'))
                        {
                            exc_r = Number($(this).val());
                        }
                        else if($(this).hasClass('scharge'))
                        {
                            sc = Number($(this).val());
                        }
                    });
                    
                    if(base && exc_r)
                    {
                        exc_rprice = Number(base * exc_r);
                        closestElem.find('.bcalculationexc').val(exc_rprice);
                       
                        let currency = closestElem.find('#currency_val').val();
                        closestElem.find('.bcalculationexcview').html(currencyConvert(currency,exc_rprice));

                    }

                    if(base && sc)
                    {
                        sc_price = Number(sc);
                        total = Number(base + sc_price);
                        closestElem.find('.bcalculationtotal').val(total);
                        closestElem.find('.bcalculationtotalview').html(currencyConvert('MYR',total));
                    }

                    if(!base || !exc_r || !sc)
                    {
                        if(!base || !exc_r)
                        {
                            closestElem.find('.bcalculationexc').val('');
                            closestElem.find('.bcalculationexcview').html('');
                        }
                        if(!base || !sc)
                        {
                            closestElem.find('.bcalculationtotal').val('');
                            closestElem.find('.bcalculationtotalview').html('');
                        }
                    }
                }
            },700);
        });


/*------------------------------------------
--------
--------END OF REMITTANCE MONEY SERVICE - JEFF
--------
-------------------------------------------*/


$(document).on('keyup',"input[name='filter[data_group]']",function(e){
    if($(this).val().includes(","))
    {
        $('#y_key').attr('disabled','disabled');
        $('#x_key').removeAttr('disabled');
        $('#x_key_div').show();
        $('#y_key_div').hide();
    }
    else if($(this).val().includes(",")==false)
    {
        $('#x_key').attr('disabled','disabled');
        $('#y_key').removeAttr('disabled');
        $('#x_key_div').hide();
        $('#y_key_div').show();
    }else{
        $('#x_key_div').show();
        $('#y_key_div').show();
    }
       
    
});



$( document ).on( 'change', '#chartType', function ()
{
    let type = $(this).val();
    
    if(type=='mixed'||type=='line'){
        $('#tension').removeAttr('disabled');
        $('#stepped').removeAttr('disabled');
        $('#fill').removeAttr('disabled');
        $('#mixed_data').removeAttr('disabled');        
        $('#stacked').removeAttr('disabled');
        $('#line_data').removeAttr('disabled');
        $('#stack_group').removeAttr('disabled');
        
        $('#stepped_div').show(); 
        $('#tension_div').show(); 
        $('#fill_div').show(); 
        $('#mixed_data_div').show();
        $('#stacked_div').show(); 
        $('#line_data_div').show();
        $('#stack_group_div').show();                     
    
    }else{
        $('#tension').attr('disabled','disabled');
        $('#stepped').attr('disabled','disabled');
        $('#fill').attr('disabled','disabled');
        $('#mixed_data').attr('disabled','disabled');
        $('#line_data').attr('disabled','disabled');
        $('#stack_group').attr('disabled','disabled');
        $('#stacked').attr('disabled');
            
        $('#stepped_div').hide(); 
        $('#tension_div').hide();
        $('#fill_div').hide();
        $('#mixed_data_div').hide();
        $('#line_data_div').hide();
        $('#stack_group_div').hide();  
        $('#stacked_div').hide();    
        
    }

    if(type=='line'){
        $('#mixed_data').attr('disabled','disabled');
        $('#line_data').attr('disabled','disabled');
        $('#stack_group').attr('disabled','disabled');
        $('#stacked').attr('disabled','disabled'); 
        $('#mixed_data_div').hide();
        $('#line_data_div').hide();
        $('#stack_group_div').hide();
        $('#stacked_div').hide();  
    }

    if(type=='pie'||type=='doughnut'){

        $('#x_key').attr('disabled','disabled');
        $('#y_key').removeAttr('disabled');
        $('#xAxisLabel').attr('disabled','disabled');
        $('#yAxisLabel').attr('disabled','disabled');
        $('#x_key_div').hide();
        $('#y_key_div').show();
        $('#xAxisLabel_div').hide();
        $('#yAxisLabel_div').hide();

    }else{
        $('#x_key').removeAttr('disabled');
        $('#xAxisLabel').removeAttr('disabled');
        $('#yAxisLabel').removeAttr('disabled');
        $('#x_key_div').show();
        $('#xAxisLabel_div').show();
        $('#yAxisLabel_div').show();
    }
    
    if(type=='bar'){
        $('#horizontal_div').show();
        $('#horizontal').removeAttr('disabled');
        $('#stacked_div').show();
        $('#stacked').removeAttr('disabled');
        $('#stack_group').removeAttr('disabled');
        $('#stack_group_div').show();
    }else{
        $('#horizontal_div').hide();
        $('#horizontal').attr('disabled','disabled');
        
               
    }
    
   
});


    });