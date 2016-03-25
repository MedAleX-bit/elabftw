<?php
/**
 * \Elabftw\Elabftw\DatabaseView
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see http://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use \Exception;
use \Datetime;

/**
 * Database View
 */
class DatabaseView extends EntityView
{
    /** object holding class Database */
    public $database;

    /** ID of the item we want to view */
    private $id;

    /** the Uploads class */
    private $uploads;

    /** Revisions class */
    private $revisions;

    /** the Status class */
    private $status;

    public $display = '';


    /**
     * Need an ID of an item
     *
     * @param Database $database
     * @throws Exception
     */
    public function __construct(Database $database)
    {
        $this->database = $database;

        $this->status = new Status();
        $this->uploads = new Uploads('items', $this->database->id);
        $this->revisions = new Revisions('items', $this->database->id);
    }

    /**
     * View item
     *
     * @return string HTML for viewDB
     */
    public function view()
    {
        $html = '';

        $html .= $this->buildView();
        $html .= $this->uploads->buildUploads('view');
        $html .= $this->buildViewJs();

        return $html;
    }
    /**
     * Edit item
     *
     * @return string HTML for editDB
     */
    public function edit()
    {
        $itemArr = $this->database->read();
        // a locked item cannot be edited
        if ($itemArr['locked']) {
            throw new Exception(_('<strong>This item is locked.</strong> You cannot edit it.'));
        }
        $html = $this->buildEdit();
        $html .= $this->uploads->buildUploadForm();
        $html .= $this->uploads->buildUploads('edit');
        $html .= $this->buildEditJs();

        return $html;
    }

    /**
     * Generate HTML for show DB
     * we have html and html2 because to build html we need the idArr
     * from html2
     *
     * @return string
     */
    public function buildShow()
    {
        $html = '';
        $html2 = '';

        // get all DB items for the team
        $itemsArr = $this->database->readAll();

        $total_time = get_total_time();

        // loop the results array and display results
        $idArr = array();
        foreach ($itemsArr as $item) {

            // fill an array with the ID of each item to use in the csv/zip export menu
            $idArr[] = $item['id'];

            $html2 .= "<section class='item" . $this->display . "' style='border-left: 6px solid #" . $item['bgcolor'] . "'>";
            $html2 .= "<a href='database.php?mode=view&id=" . $item['id'] . "'>";

            // show attached if there is a file attached
            // we need an id to look for attachment
            $this->database->id = $item['id'];
            if ($this->database->hasAttachment('items')) {
                $html2 .= "<img style='clear:both' class='align_right' src='img/attached.png' alt='file attached' />";
            }
            // STARS
            $html2 .= show_stars($item['rating']);
            $html2 .= "<p class='title'>";
            // LOCK
            if ($item['locked'] == 1) {
                $html2 .= "<img style='padding-bottom:3px;' src='img/lock-blue.png' alt='lock' />";
            }
            // TITLE
            $html2 .= stripslashes($item['title']) . "</p></a>";
            // ITEM TYPE
            $html2 .= "<span style='text-transform:uppercase;font-size:80%;padding-left:20px;color:#" . $item['bgcolor'] . "'>" . $item['name'] . " </span>";
            // DATE
            $html2 .= "<span class='date' style='padding:0 5px;'><img class='image' src='img/calendar.png' /> " . Tools::formatDate($item['date']) . "</span> ";
            // TAGS
            $html2 .= show_tags($item['id'], 'items_tags');

            $html2 .= "</section>";
        }

        // show number of results found
        $count = count($itemsArr);
        if ($count === 0 && $this->database->searchType != 'none') {
            return display_message('ko_nocross', _("Sorry. I couldn't find anything :("));
        } elseif ($count === 0 && $this->database->searchType === '') {
            return display_message('ok', _('<strong>Welcome to eLabFTW.</strong> Select an item in the «Create new» list to begin filling your database.'));
        } else {
            $html .= "<div class='align_right'>";
            $html .= "<a name='anchor'></a>";
            $html .= "<p class='inline'>" . _('Export this result:') . " </p>";
            $html .= "<a href='make.php?what=zip&id=" . Tools::buildStringFromArray($idArr) . "&type=items'>";
            $html .= " <img src='img/zip.png' title='make a zip archive' alt='zip' /></a>";
            $html .= "<a href='make.php?what=csv&id=" . Tools::buildStringFromArray($idArr) . "&type=items'>";
            $html .= " <img src='img/spreadsheet.png' title='Export in spreadsheet file' alt='Export CSV' /></a></div>";
            $html .= "<p class='smallgray'>" . $count . " " .
                ngettext("result found", "results found", $count) . " (" .
                $total_time['time'] . " " . $total_time['unit'] . ")</p>";
        }
        return $html . $html2;
    }

    public function buildShowMenu()
    {
        $itemsTypes = new ItemsTypes($this->database->team);
        $itemsTypesArr = $itemsTypes->read();

        $html = "<menu class='border'>";
        $html .= "<div class='row'>";
        $html .= "<div class='col-md-2'>";
        $html .= "<form class='form-inline pull-left'>";
        // CREATE NEW dropdown menu
        $html .= "<select class='form-control select-create-db' onchange='go_url(this.value)'>";
        $html .= "<option value=''>" . _('Create new') . "</option>";
        foreach ($itemsTypesArr as $itemType) {
            $html .= "<option value='app/controllers/DatabaseController.php?databaseCreateId=" . $itemType['id'] . "'";
            $html .= ">" . $itemType['name'] . "</option>";
        }
        $html .= "</select></form></div>";

        // FILTER
        $html .= "<div class='col-md-10'>";
        $html .= "<form class='form-inline pull-right'>";
        $html .= "<div class='form-group'>";
        $html .= "<input type='hidden' name='mode' value='show' />";
        $html .= "<input type='hidden' name='tag' value='" . $this->database->tag . "' />";
        $html .= "<input type='hidden' name='q' value='" . $this->database->query . "' />";
        $html .= "<select name='filter' class='form-control select-filter-cat'>";
        $html .= "<option value=''>" . _('Filter type') . "</option>";
        foreach ($itemsTypesArr as $itemType) {
            $html .= "<option value='" . $itemType['id'] . "'" . checkSelectFilter($itemType['id']) . ">" . $itemType['name'] . "</option>";
        }
        $html .= "</select>";
        $html .= "<button class='btn btn-elab submit-filter'>" . _('Filter') . "</button>";

        // ORDER
        $html .= "<select name='order' class='form-control select-order'>";
        $html .= "<option value=''>" . _('Order by') . "</option>";
        $html .= "<option value='cat'" . checkSelectOrder('cat') . ">" . _('Category') . "</option>";
        $html .= "<option value='date'" . checkSelectOrder('date') . ">" . _('Date') . "</option>";
        $html .= "<option value='rating'" . checkSelectOrder('rating') . ">" . _('Rating') . "</option>";
        $html .= "<option value='title'" . checkSelectOrder('title') . ">" . _('Title') . "</option>";
        $html .= "</select>";

        // SORT
        $html .= "<select name='sort' class='form-control select-sort'>";
        $html .= "<option value=''>" . _('Sort') . "</option>";
        $html .= "<option value='desc'" . checkSelectSort('desc') . ">" . _('DESC') . "</option>";
        $html .= "<option value='asc'" . checkSelectSort('asc') . ">" . _('ASC') . "</option>";
        $html .= "</select>";
        $html .= "<button class='btn btn-elab submit-order'>" . _('Order') . "</button>";
        $html .= "<button type='reset' class='btn btn-danger submit-reset' onclick=\"javascript:location.href='database.php?mode=show&tag="
            . $this->database->tag . "&q="
            . $this->database->query . "&filter="
            . $this->database->filter . "';\">" . _('Reset') . "</button></div></form></div>";

        $html .= "</div></menu>";

        return $html;
    }

    /**
     * Generate HTML for view DB
     *
     * @return string
     */
    private function buildView()
    {
        $itemArr = $this->database->read();

        $html = "<section class='box'>";
        $html .= "<span class='date_view'><img src='img/calendar.png' title='date' alt='Date :' /> ";
        $html .= Tools::formatDate($itemArr['date']) . "</span><br>";
        $html .= show_stars($itemArr['rating']);
        // buttons
        $html .= "<a href='database.php?mode=edit&id=" . $itemArr['itemid'] . "'><img src='img/pen-blue.png' title='edit' alt='edit' /></a> 
        <a href='app/controllers/DatabaseController.php?databaseDuplicateId=" . $itemArr['itemid'] . "'><img src='img/duplicate.png' title='duplicate item' alt='duplicate' /></a> 
        <a href='make.php?what=pdf&id=" . $itemArr['itemid'] . "&type=items'><img src='img/pdf.png' title='make a pdf' alt='pdf' /></a> 
        <a href='make.php?what=zip&id=" . $itemArr['itemid'] . "&type=items'><img src='img/zip.png' title='make a zip archive' alt='zip' /></a>
        <a href='experiments.php?mode=show&related=".$itemArr['itemid'] . "'><img src='img/link.png' alt='Linked experiments' title='Linked experiments' /></a> ";
        // lock
        if ($itemArr['locked'] == 0) {
            $html .= "<a href='app/lock.php?id=" . $itemArr['itemid'] . "&action=lock&type=items'><img src='img/unlock.png' title='lock item' alt='lock' /></a>";
        } else { // item is locked
            $html .= "<a href='app/lock.php?id=" . $itemArr['itemid'] . "&action=unlock&type=items'><img src='img/lock-gray.png' title='unlock item' alt='unlock' /></a>";
        }
        // TAGS
        $html .= " " . show_tags($this->database->id, 'items_tags');

        // TITLE : click on it to go to edit mode
        $html .= "<div ";
        if ($itemArr['locked'] == 0) {
            $html .= " onClick=\"document.location='database.php?mode=edit&id=" . $itemArr['itemid'] . "'\" ";
        }
        $html .= "class='title_view'>";
        $html .= "<span style='color:#" . $itemArr['bgcolor'] . "'>" . $itemArr['name'] . " </span>";
        $html .= stripslashes($itemArr['title']);
        $html .= "</div>";
        // BODY (show only if not empty)
        if ($itemArr['body'] != '') {
            $html .= "<div onClick='go_url(\"database.php?mode=edit&id=" . $itemArr['itemid'] . "\")'";
            $html .= " id='body_view' class='txt'>" . stripslashes($itemArr['body']) . "</div>";
        }
        // SHOW USER
        $html .= _('Last modified by') . ' ' . $itemArr['firstname'] . " " . $itemArr['lastname'];
        $html .= "</section>";

        return $html;
    }

    /**
     * Generate JS code for view DB
     *
     * @return string
     */
    private function buildViewJs()
    {
        $html = '';
        if ($_SESSION['prefs']['chem_editor']) {
            $html .= "<script src='js/chemdoodle.js'></script>
            <script src='js/chemdoodle-uis.js'></script>
                    <script>
                        ChemDoodle.iChemLabs.useHTTPS();
                    </script>";
        }

        return $html;
    }

    /**
     * Generate HTML for edit DB
     *
     * @return string
     */
    private function buildEdit()
    {
        $itemArr = $this->database->read();

        // load tinymce
        $html = "<script src='js/tinymce/tinymce.min.js'></script>";

        // begin page
        $html .= "<section class='box' style='border-left: 6px solid #" . $itemArr['bgcolor'] . "'>";
        $html .= "<img class='align_right' src='img/big-trash.png' title='delete' alt='delete' onClick=\"deleteThis('" . $this->database->id . "','item', 'database.php')\" />";

        // tags
        $html .= displayTags('items', $this->database->id);

        // main form
        $html .= "<form method='post' action='app/controllers/DatabaseController.php' enctype='multipart/form-data'>";
        $html .= "<input name='databaseUpdate' type='hidden' value='true' />";
        $html .= "<input name='databaseId' type='hidden' value='" . $this->database->id . "' />";

        // date
        $html .= "<div class='row'>";
        $html .= "<div class='col-md-4'>";
        $html .= "<img src='img/calendar.png' class='bot5px' title='date' alt='Date :' />";
        $html .= "<label for='datepicker'>" . _('Date') . "</label>";
        // TODO if firefox has support for it: type = date
        $html .= "<input name='databaseUpdateDate' id='datepicker' size='8' type='text' value='" . $itemArr['date'] . "' />";
        $html .= "</div></div>";

        // star rating
        $html .= "<div class='align_right'>";
        for ($i = 1; $i < 6; $i++) {
            $html .= "<input id='star" . $i . "' name='star' type='radio' class='star' value='" . $i . "'";
            if ($itemArr['rating'] == $i) {
                $html .= 'checked=checked';
            }
            $html .= "/>";
        }
        $html .= "</div>";

        // title
        $html .= "<h4>" . _('Title') . "</h4>";
        $html .= "<input id='title_input' name='databaseUpdateTitle' rows='1' value='" . stripslashes($itemArr['title']) . "' required />";

        // body
        $html .= "<h4>" . _('Infos') . "</h4>";
        $html .= "<textarea class='mceditable' name='databaseUpdateBody' rows='15' cols='80'>";
        $html .= stripslashes($itemArr['body']);
        $html .= "</textarea>";

        // submit button
        $html .= "<div class='center' id='saveButton'>";
        $html .= "<button type='submit' name='Submit' class='button'>";
        $html .= _('Save and go back') . "</button></div></form>";

        // revisions
        $html .= $this->revisions->showCount();

        $html .= "</section>";

        $html .= $this->injectChemEditor();

        return $html;
    }

    /**
     * Build the JS code for edit mode
     *
     * @return string
     */
    private function buildEditJs()
    {
        $tags = new Tags('items');

        $html = "<script>
        // DELETE TAG
        function delete_tag(tag_id,item_id){
            var you_sure = confirm('" . _('Delete this?') . "');
            if (you_sure == true) {
                $.post('app/delete.php', {
                    id:tag_id,
                    item_id:item_id,
                    type:'itemtag'
                })
                .success(function() {
                    $('#tags_div').load('database.php?mode=edit&id=' + item_id + ' #tags_div');
                })
            }
            return false;
        }

        // READY ? GO !
        $(document).ready(function() {
            // ADD TAG JS
            // listen keypress, add tag when it's enter
            $('#createTagInput').keypress(function (e) {
                createTag(e, " . $this->database->id . ", 'items');
            });

            // autocomplete the tags
            $('#createTagInput').autocomplete({
                source: [" . $tags->generateTagList() . "]
            });

            // If the title is 'Untitled', clear it on focus
            $('#title_input').focus(function(){
                if ($(this).val() === 'Untitled') {
                    $('#title_input').val('');
                }
            });

            // EDITOR
            tinymce.init({
                mode : 'specific_textareas',
                editor_selector : 'mceditable',
                content_css : 'css/tinymce.css',
                plugins : 'table textcolor searchreplace code fullscreen insertdatetime paste charmap save image link pagebreak mention',
                pagebreak_separator: '<pagebreak>',
                toolbar1: 'undo redo | bold italic underline | fontsizeselect | alignleft aligncenter alignright alignjustify | superscript subscript | bullist numlist outdent indent | forecolor backcolor | charmap | image link | save',
                removed_menuitems : 'newdocument',
                // save button :
                save_onsavecallback: function() {
                    $.post('app/quicksave.php', {
                        id : " . $this->database->id . ",
                        type : 'items',
                        // we need this to get the updated content
                        title : document.getElementById('title_input').value,
                        date : document.getElementById('datepicker').value,
                        body : tinymce.activeEditor.getContent()
                    }).success(function(data) {
                        if (data == 1) {
                            notif('" . _('Saved') . "', 'ok');
                        } else {
                            notif('" . _('Something went wrong! :(') . "', 'ko');
                        }
                    });
                },
                // keyboard shortcut to insert today's date at cursor in editor
                setup : function(editor) {
                    editor.addShortcut('ctrl+shift+d', 'add date at cursor', function() { addDateOnCursor(); });
                },
                language : '" . $_SESSION['prefs']['lang'] . "',
                mentions: {
                    source: [" . getDbList('mention') . "],
                    delimiter: '#'
                },
                style_formats_merge: true,
                style_formats: [
                    {
                        title: 'Image Left',
                        selector: 'img',
                        styles: {
                            'float': 'left',
                            'margin': '0 10px 0 10px'
                        }
                     },
                     {
                         title: 'Image Right',
                         selector: 'img',
                         styles: {
                             'float': 'right',
                             'margin': '0 0 10px 10px'
                         }
                     }
                ]

            });
        // DATEPICKER
        $( '#datepicker' ).datepicker({dateFormat: 'yymmdd'});
        // STARS
        $('input.star').rating();";
        for ($i = 1; $i < 6; $i++) {
            $html .= "$('#star" . $i . "').click(function() {
            updateRating(" . $i . ", " . $this->database->id . ");
        });";
        }

        $html .= $this->injectCloseWarning();
        $html .= "});</script>";

        return $html;

    }
}