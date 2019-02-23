<?php
/**
 * @var $this yii\web\View
 * @var $categories \app\models\Categories
 */
?>
<table id="book-list"></table>
<div id="book-pager"></div>

<script type="text/javascript">
	//INSPIRATION: http://stackoverflow.com/a/4842450/2032121
	//SOLUTION: http://stackoverflow.com/a/4073967/2032121
	//SOLUTION: http://www.trirand.com/blog/?page_id=393/help/to-get-the-rowid-of-the-nth-row-of-the-grid/
	//SOLUTION: http://stackoverflow.com/a/9545050/2032121
	//API: http://www.w3.org/TR/FileAPI/
	//identifying which image is currently open in fancybox
	//https://github.com/fancyapps/fancyBox/issues/40
	//$.jgrid.no_legacy_api = true;

	var book_list = $("#book-list"), lastSel, lastFavorite, lastRead, filterCategories;

	function getCookie(n,v) {
		var val = Cookies.get(n);
		if (val == undefined) {
			val = v;
		}
		//console.log('cookie:',n,v);
		return val;
	}

	window.onbeforeunload = function() {
		Cookies.set('rowNum', book_list.jqGrid('getGridParam', 'rowNum'));
		Cookies.set('page', book_list.jqGrid('getGridParam', 'page'));
	};

	var ratyOptions = {
		score: function () { return $(this).attr('data-score'); },
		click: function (score, evt) {
			$(this).attr('data-score', score);
			$(this).attr('data-write', 'false');
		},
		readOnly: function () {
			return ("false" == $(this).attr('data-write'));
		},
		space:false,
		number: 5,
		cancel: false,
		half: true,
		path: '<?= Yii::getAlias('@web/assets/jquery-raty/img/')?>'
	};

	book_list.jqGrid({
		url:'<?php echo Yii::$app->getUrlManager()->createUrl('api/book'); ?>',
		editurl : '<?php echo  Yii::$app->getUrlManager()->createUrl('api/book/manage');?>',
		datatype: "json",
		colNames:[
			'<?php echo \Yii::t('frontend/site', 'Added'); ?>', '',
			'<?php echo \Yii::t('frontend/site', 'Favorite'); ?>',
			'<?php echo \Yii::t('frontend/site', 'Read'); ?>',
			'<?php echo \Yii::t('frontend/site', 'Year'); ?>',
			'<?php echo \Yii::t('frontend/site', 'Title'); ?>',
			'<?php echo \Yii::t('frontend/site', 'ISBN-13'); ?>',
			'<?php echo \Yii::t('frontend/site', 'Author'); ?>',
			'<?php echo \Yii::t('frontend/site', 'Publisher'); ?>',
			'<?php echo \Yii::t('frontend/site', 'Extension'); ?>',
			'', ''],
		colModel:[
			{ name:'created_date', index:'created_date', width: 80, editable: false, formatter: "date", formatoptions: { srcformat: "ISO8601Long", newformat: "d-m-Y" }, },
			// we use book guid as book cover column!
			{ name: 'book_guid',index: 'book_guid', width: 21, editable: false, frozen: true, align: 'center', search: false, sortable: false,
				formatter: function(cellvalue, options, rowObject) {
					return '<a class="book-cover-link" data-guid="'+options.rowId+
						'" data-fancybox-group="book-covers" href="<?php echo Yii::$app->getUrlManager()->createUrl(['api/book/cover', 'book_guid' => '']); ?>' + options.rowId +
						 '" title="'+rowObject[4]+' : '+rowObject[5]+'"'+'><img class="book-cover" src="<?php echo Yii::$app->getUrlManager()->createUrl(['api/book/cover', 'book_guid' => '']); ?>' +
						options.rowId + '" /></a>';
				}
			},
			{ name: 'favorite', index: 'favorite', width: 80, search: false,
				editable: true,
				formatter: function(cellvalue, options, rowObject) {
					return '<div class="book-favorite" data-write="false" data-guid="'+options.rowId+'" data-score="'+cellvalue+'" />';
				},
				unformat: function (cellvalue, options) {//BUG: cellvalue is empty!
					return lastFavorite;
				},
				edittype: 'custom',
				editoptions: {
					custom_element: function (value, options) {
						var element = $('<div class="book-favorite" data-write="true" data-score="'+value+'"></div>').raty(ratyOptions)[0];
						return element;
					},
					custom_value: function (element) {
						return element.attr('data-score');
					}
				}
			},
			{ name:'read',index:'read', width:60,
				stype: 'select', search: true, searchoptions: {
					sopt: ['eq'],
					value: ':<?php echo \Yii::t('frontend/site', 'All'); ?>;yes:<?php echo \Yii::t('frontend/site', 'Yes'); ?>;no:<?php echo \Yii::t('frontend/site', 'No'); ?>'
				},
				formatter: function(cellvalue, options, rowObject) {
					lastRead = cellvalue;
					return (cellvalue == 'yes' ? '<div class="book-read-yes"/>' : '');
				},
				unformat: function (cellvalue, options) {
					return lastRead;
				},
				edittype: 'custom',
				editoptions: {
					custom_element: function (value, options) {
						return '<input type="checkbox" '+ ( value == 'yes' ? 'checked="checked"' : '') +'/>';
					},
					custom_value: function (element) {
						return element.is(':checked') ? 'yes' : 'no';
					}
				}
			},
			{ name:'year',index:'year', width:50 },
			{ name:'title',index:'title', width: 400, align: 'left'},
			{ name:'isbn13', index:'isbn13', width: 115},
			{ name:'author', index:'author', width: 150, align: 'left'},
			{ name:'publisher', index:'publisher', width:150, align: 'left' },
			{ name:'ext', index:'ext', width: 80, align: 'center'},
			{ name:'filename', index:'filename', width: 20, align: 'center', search: false, resize: false, sortable: false, editable: false,
				formatter: function(cellvalue, options, rowObject) {
			  console.log('<?php echo strtoupper(PHP_OS)?>')
			        <?php echo (strtoupper(PHP_OS) == 'LINUX' ?
                        // replace ' ' to '\ ' AND replace "'" to "\'"
                        'cellvalue = cellvalue.replace(/\s/g, \'\\\\ \'); cellvalue = cellvalue.replace(/\'/g, "\\\\\'");'
                        : ''); ?>
					return '<span title="click to copy filename to clipboard" class="book-filename ui-icon ui-icon-document" data-filename="<?php echo str_replace('\\', '\\\\', Yii::$app->mycfg->library->directory); ?>'+cellvalue+'"></span>';
				}
			},
			{ name: 'myac', width:40, fixed: true, sortable: false, editable: false, search: false, resize: false, formatter:'actions', formatoptions: { keys: true, editbutton: false } }
		],
		cmTemplate: {align: 'center', sortable: true, editable: true, hidden: false},
		caption: '',
		rowNum: parseInt(getCookie('rowNum', 10)),
		page: parseInt(getCookie('page', 1)),
		//TODO: currently store in cookies only page: <?php //echo \Yii::$app->session->get('jqgrid.page', 1); ?>,
		rownumbers: true,
		autowidth: true,
		height: '100%',
		rowList: [10,20,30,40,50],
		pager: jQuery('#book-pager'),
		sortname: 'created_date',
		viewrecords: true,
		sortorder: "desc",
		sortable: true,
		//support cell edit
		cellEdit: false,
		cellSubmit: 'remote',
		cellurl: '<?php echo  Yii::$app->getUrlManager()->createUrl('api/book/manage');?>',
        subGrid: true,
        subGridRowExpanded: function(subgrid_id, row_id) {
            // If we wan to pass additinal parameters to the url we can use
            // a method getRowData(row_id) - which returns associative array in type name-value
            var subgrid_table_id = subgrid_id+"_t";
            var pager_id = "p_"+subgrid_table_id;
            $("#"+subgrid_id).html("<table id='"+subgrid_table_id+"' class='scroll'></table><div id='"+pager_id+"' class='scroll'></div>");
            jQuery("#"+subgrid_table_id).jqGrid({
                caption: 'Categories',
                rownumbers: true,
                url:'<?php echo Yii::$app->getUrlManager()->createUrl('api/category?nodeid='); ?>'+row_id,
                editurl : '<?php echo  Yii::$app->getUrlManager()->createUrl('api/category/manage');?>',
                datatype: "json",
                colNames: ['title', 'marker'],
                colModel: [
                    {name:"title",index:"title", width: 200, editable: true},
                    {name:"marker", index:"marker", formatter:"checkbox",edittype: "checkbox", editable: true, editoptions: { value:"1:0"} }
                ],
                cellEdit: true,
                cellSubmit: 'remote',
                cellurl: '<?php echo Yii::$app->getUrlManager()->createUrl('api/category/manage?nodeid='); ?>'+row_id,
                sortorder: "asc",
                rowNum:20,
                pager: pager_id,
                height: '100%',
                sortorder: "asc",
                sortname: 'title'
            });
            jQuery("#"+subgrid_table_id).jqGrid('navGrid',"#"+pager_id,{edit:true,add:true,del:true});
        },
        // subGridRowColapsed: function(subgrid_id, row_id) .. // this function is called before removing the data
        toolbar: [true, "bottom"],
        loadComplete: function() {
		    // support category filters as checkboxes
		    if (filterCategories == null) {
                var $toolbar = $('#t_'+book_list.attr('id'));
                $widget = $('<div id="category_list" style="user-select: none;">');
                $widget.append($('<input id="category_ALL" type="checkbox" value="false" /> <label for="category_ALL">[ALL]</label>'));
                <?php foreach ($categories as $category): ?>
                $widget.append($('<input id="category_<?=$category->guid;?>" type="checkbox" value="<?=$category->guid;?>" /> <label for="category_<?=$category->guid;?>"><?=$category->title;?></label>'));
                <?php endforeach; ?>
                $toolbar.append($widget);

                $("#category_list").buttonset();
                $("#category_list input").click(function() {
                    var id = $(this).attr('id');

                    if (id == 'category_ALL') { // select all
                        if ($(this).prop('checked')) {
                            var category_ids = $('#category_list input:not(#category_ALL)').map(function() {
                                return $(this).val();
                            });
                            $('#category_list input').prop('checked', false);
                            $('#category_list input#category_ALL').prop('checked', true);

                            filterCategories = Array.prototype.join.call(category_ids, ",");
                        } else {
                            filterCategories = 0;
                        }
                    } else {
                        // reset ALL
                        $('#category_list input#category_ALL').prop('checked', false);

                        var category_ids = $('#category_list input:checked').map(function() {
                            return $(this).val();
                        });
                        filterCategories = Array.prototype.join.call(category_ids, ",");
                    }

                    $("#category_list input").button('refresh');

                    book_list.jqGrid('setGridParam', {
                        url: '<?php echo Yii::$app->getUrlManager()->createUrl('api/book'); ?>?filterCategories='+filterCategories,
                        page:1
                    }).trigger("reloadGrid");
                });

                filterCategories = 0; // avoid another append
            }

			$(".book-filename").on('click', function(e){
				window.prompt ("Copy to clipboard: Ctrl+C, Enter", $(this).attr('data-filename'));
			});

			$(".book-favorite").raty(ratyOptions); //rating init

			$(".book-cover-link").fancybox({
				type: 'image',
				/*tpl: {wrap:
				 '<div class="fancybox-wrap" tabIndex="-1">'+
				 '<div class="fancybox-skin">'+
				 '<div class="fancybox-outer">'+
				 '<div class="fancybox-inner book-cover-holder">'+
				 '</div>'+
				 '</div>'+
				 '</div>'+
				 '</div>
				 },'*/
				afterLoad: function(e) {
					var book_guid = $(this.element[0]).attr('data-guid'); //1st element in visible group, we show 1 by 1
					coverUpload.init(book_guid);
				},
				afterClose: function(e) {
				}
			});
		},
		ondblClickRow: function(rowid, ri, ci) {
			var row_obj = book_list.jqGrid('getInd', rowid, true); // row_obj is just piece of html - TR
			lastFavorite = $(row_obj).find('div.book-favorite').attr('data-score');
			lastRead = $(row_obj).find('div.book-read-yes').attr('class') !== undefined ? 'yes' : 'no';
			//console.log(row_obj);

			//.jqGrid('editRow', rowid, keys, oneditfunc, successfunc, url, extraparam, aftersavefunc,errorfunc, afterrestorefunc);
			book_list.jqGrid('editRow', rowid, {
				keys: true,
				aftersavefunc: function (id, response, options) {
					$('.book-favorite[data-guid=\"'+id+'\"]').raty(ratyOptions);//SOLUTION:http://stackoverflow.com/a/6246687/2032121
				},
				afterrestorefunc: function (id, response, options) {
					$('.book-favorite[data-guid="'+id+'"]').raty(ratyOptions);
				}
			});
		},

		onSelectRow: function(id) {
			if (id && id !== lastSel) {
				if (typeof lastSel !== "undefined") {
					book_list.jqGrid('restoreRow',lastSel);
					//$(".book-favorite").raty(ratyOptions); BUG: overhead!
				}
				lastSel = id;
			}
		}
	})
	.jqGrid('navGrid','#book-pager', { edit: false, add: true, del: false, search: false } )// navigation: default
	.jqGrid('filterToolbar', { stringResult: true, searchOnEnter: false });// inline filter
</script>


<script type="text/javascript">
//
// Book Cover Management
//
var coverUpload = {
	init: function(book_guid) {
		$(".fancybox-inner").addClass("book-cover-holder").append('<div class="book-cover-drop">drop<br /><b>HERE</b></div>');

		$('.book-cover-drop')
		.on('dragover',  function(e){ e.preventDefault(); $(this).addClass('hovered');})
		.on('dragleave', function(e){ e.preventDefault(); $(this).removeClass('hovered');})
		.on('dragenter', function(e){ e.preventDefault(); })
		.on('save-file', function (e, file) {
			if($.inArray(file.type, ['image/png', 'image/jpeg', 'image/gif']) == -1) {
				alert('Sorry, you can use only GIF, JPEG and PNG images');
				return;
			}
			var xhr = new XMLHttpRequest();
			xhr.open('POST','<?php echo Yii::$app->getUrlManager()->createUrl(['api/book/cover-save','book_guid' => '']);?>'+book_guid,true);
			xhr.send(file);
			xhr.onreadystatechange = function(e){
				if(this.readyState != 4 || this.status !=200) return;
				//refresh view
				var r = new FileReader();
				r.readAsDataURL(file);
				r.onload = function(e){
					$('.book-cover-holder > img').attr('src', e.target.result);
				};
				r.onprogress = function(e){
					if (e.lengthComputable) {
						$('.book-cover-progress').val(e.loaded/e.total*100);
					}
				};
			};//xhr
		})
		.on('drop', function (e){
			e = e.originalEvent;
			e.preventDefault();
			var file = e.dataTransfer.files[0];
			$(this).trigger('save-file', file);
		});
	}//init()
};

// cover was pasted from browser image clipboard
$(document).on('paste', function(e) {
	var cover = $('.book-cover-drop:visible')[0];
	if (cover !== undefined) {
		//TODO: copy from filesystem clipboard
		e = e.originalEvent;
		e.preventDefault();
		$cover = $(cover);
		var items = e.clipboardData.items;
    for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf("image") !== -1) {
           // We need to represent the image as a file,
           var file = items[i].getAsFile();
           console.log('on paste');
           $cover.trigger('save-file', file);
           console.log(file);
        }
     }
	}// if cover is visible
});
</script>
