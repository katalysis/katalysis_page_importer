<?php
defined('C5_EXECUTE') or die('Access Denied.');

$token = Core::make('token');

/**
 * @var Packages\KatalysisPageImporter\Controller\SinglePage\Dashboard\System\KatalysisPageImporter\PageImporter $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var int $segmentMaxLength
 */


use Concrete\Core\Support\Facade\Application;
$app = Application::getFacadeApplication();
$form = $app->make('helper/form');
$ps = $app->make('helper/form/page_selector');

$formAction = $controller->action('process_import');
if ($controller->getAction() == 'review_import') {
    $formAction = $controller->action('import', $pageTypeID, $pageTemplateID, $parentPageID, $csvID);
}

?>

<form method="post" enctype="multipart/form-data" action="<?= $formAction ?>">
    <div id="ccm-dashboard-content-inner">
        <div class="row justify-content-between mb-5">
            <div class="col-md">
                <?php if ($controller->getAction() == 'review_import') { ?>
                    <?php
                    $csvArray = $controller->get('csvArray');
                    if ($csv) {
                        echo '<h3>Import Preview - check carefully</h3>';
                        //print_r($csv->data);
                        ?>
                        <div class="alert alert-danger p-2">
                            <strong>Warning:</strong> Pages can only deleted individually so test your import on a small number of pages to start with.
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <tr>
                                    <?php foreach ($csv->titles as $value): ?>
                                        <th><?php echo $value; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                <?php foreach ($csv->data as $key => $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?php echo $value; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                   <?php }
            
                } else if ($controller->getAction() !== 'import') { ?>

                        <fieldset class="mb-5">
                            <div class="row">
                                <div class="col-md-6">

                                    <div class="form-group">
                                        <label for="parentPage"
                                            class="control-label form-label"><?php echo t('Parent Page') ?></label>
                                    <?= $ps->selectPage('parentPage', isset($parentPage) ? $parentPage : null); ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="page" class="control-label form-label"><?php echo t('Page Type') ?></label>
                                    <?php
                                    echo '<select class="form-select" id="pageType" name="pageType">';
                                    echo '<option value="">Select Page Type...</option>';
                                    foreach ($pageTypes as $cm) {
                                        echo '<option value="' . $cm->getPageTypeID() . '">' . $cm->getPageTypeDisplayName() . '</option>';
                                    }
                                    echo '</select>'
                                        ?>
                                    </div>

                                    <script>
                                        $('#pageType').on('change', function () {
                                            var selectedPageType = $('option:selected', this).val();
                                            $('#pageTemplate').empty();
                                            document.getElementById("pageTemplate").setAttribute('disabled', 'disabled');
                                            $.ajax({
                                                type: "POST",
                                                url: "<?= $view->action('get_page_templates') ?>",
                                                data: {
                                                    pageTypeID: selectedPageType
                                                },
                                                success: function (data) {
                                                    $.each(data, function (i, d) {
                                                        $('#pageTemplate').append('<option value="' + d.pTemplateID + '">' + d.pTemplateName + '</option>');
                                                    });
                                                    document.getElementById("pageTemplate").removeAttribute('disabled');
                                                }
                                            });
                                        })
                                    </script>

                                    <div class="form-group">
                                        <label for="pageTemplate"
                                            class="control-label form-label"><?php echo t('Page Template') ?></label>
                                    <?php
                                    echo '<select class="form-select" id="pageTemplate" name="pageTemplate" disabled>';
                                    echo '</select>'
                                        ?>
                                    </div>
                                    <?php
                                    $csv = false;
                                    if (isset($csvID) && $csvID) {
                                        $csv = \File::getByID($csvID);
                                    }
                                    ?>
                                    <div class="form-group">
                                        <label class="control-label form-label">CSV File</label>
                                        <?php
                                        $service = Core::make('helper/concrete/file_manager');
                                        print $service->file('csv', 'csv', 'Select CSV File', $csv);
                                        ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="help-block" style="font-style:normal;">
                                        <h4>CSV File Requirements</h4>
                                        <ul>
                                            <li>First row must contain at least the three required column headers:
                                                <ul>
                                                    <li><strong>Page Name</strong></li>
                                                    <li><strong>Page Description</strong></li>
                                                    <li><strong>URL Slug</strong></li>
                                                </ul>
                                            </li>
                                            <li>Optionally you may also include:
                                                <ul>
                                                    <li><strong>Meta Title</strong></li>
                                                    <li><strong>Meta Description</strong></li>
                                                </ul>
                                            </li>
                                            <li>Column headers must be unique</li>
                                            <li>Column headers must not contain special characters except as specified below</li>
                                            <li>Avoid leaving empty columns without Headers in the csv file</li>
                                        </ul>
                                        <p>Any other columns will be used in <em>find and replace</em> (see below)</p>
                                        <h4>Find & Replace Placeholders</h4>
                                        <p>Use the following format to add <em>find and replace</em> columns in the csv file:</p>
                                        <ul>
                                            <li>Column header must be contained in square brackets <strong>[ ]</strong></li>
                                            <li>If the column contains a comma separated list to format add a hypen before the opening square bracket <strong>-[</strong></li>
                                            <li>Example: <strong>[Single Item]</strong> or <strong>-[Item List]</strong></li>
                                        </ul>
                                        <h4>Add Placeholders to your Page Type defaults</h4>
                                        <ul>
                                            <li>Enable the Placeholder plugin for the <a href="/index.php/dashboard/system/basics/editor" target="_blank">editor</a></li>
                                            <li>Go to <a href="/index.php/dashboard/pages/types" target="_blank">Page Types</a></li>
                                            <li>Select the Page Type and Page Template you are importing</li>
                                            <li>Insert Placeholders in Content blocks anywhere you want to replace content</li>
                                            <li><strong>Placeholder names must match the corresponding headers in your csv file exactly.</strong></li>
                                        </ul>
                                        <div class="alert alert-danger p-2">
                                            <strong>Warning:</strong> Pages can only deleted individually so test your import on a small number of pages to start with.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <?php if ($controller->getAction() == ('view') || $controller->getAction() == ('process_import')) { ?>
                <?php echo $form->submit('review_import', t('Review Import'), ['class' => 'btn btn-primary float-end']); ?>
            <?php } else if ($controller->getAction() == ('review_import')) { ?>
                <?php echo $form->submit('process_import', t('Import'), ['class' => 'btn btn-danger float-end']); ?>
            <?php } ?>
        </div>
    </div>
</form>
