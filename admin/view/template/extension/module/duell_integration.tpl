 <?php echo $header; ?>
  <?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-featured" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> <?php echo $text_about; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
      </div>
      <div class="panel-body">





        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-html" class="form-horizontal">

          <div class="form-group">
            <label class="col-sm-2 control-label" for="duell_integration_client_number"><span data-toggle="tooltip" title="<?php echo $help_text_duell_integration_client_number; ?>"><?php echo $text_duell_integration_client_number; ?></span></label>
            <div class="col-sm-10">
              <input type="number" name="duell_integration_client_number" value="<?php echo $duell_integration_client_number; ?>" placeholder="<?php echo $text_duell_integration_client_number; ?>" class="form-control" />
               <?php if ($error_duell_integration_client_number) { ?>
              <div class="text-danger"><?php echo $error_duell_integration_client_number; ?></div>
              <?php } ?>
            </div>
          </div>


          <div class="form-group">
            <label class="col-sm-2 control-label" for="duell_integration_client_token"><span data-toggle="tooltip" title="<?php echo $help_text_duell_integration_client_token; ?>"><?php echo $text_duell_integration_client_token; ?></span></label>
            <div class="col-sm-10">
              <input type="text" name="duell_integration_client_token" value="<?php echo $duell_integration_client_token; ?>" placeholder="<?php echo $text_duell_integration_client_token; ?>" class="form-control" />
               <?php if ($error_duell_integration_client_token) { ?>
              <div class="text-danger"><?php echo $error_duell_integration_client_token; ?></div>
              <?php } ?>
            </div>
          </div>


          <div class="form-group">
            <label class="col-sm-2 control-label" for="duell_integration_department_token"><span data-toggle="tooltip" title="<?php echo $help_text_duell_integration_department_token; ?>"><?php echo $text_duell_integration_department_token; ?></span></label>
            <div class="col-sm-10">
              <input type="text" name="duell_integration_department_token" value="<?php echo $duell_integration_department_token; ?>" placeholder="<?php echo $text_duell_integration_department_token; ?>" class="form-control" />
               <?php if ($error_duell_integration_department_token) { ?>
              <div class="text-danger"><?php echo $error_duell_integration_department_token; ?></div>
              <?php } ?>
            </div>
          </div>





          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
            <div class="col-sm-10">
              <select name="duell_integration_status" id="input-status" class="form-control">
                <?php if ($duell_integration_status) { ?>
                <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                <option value="0"><?php echo $text_disabled; ?></option>
                <?php } else { ?>
                <option value="1"><?php echo $text_enabled; ?></option>
                <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>


          <div class="form-group">
            <label class="col-sm-2 control-label" for="">&nbsp;</label>
            <div class="col-sm-10">
              <button type="button" id="button-syncmanually" data-toggle="tooltip" title="<?php echo $text_duell_integration_manual_sync; ?>" class="btn btn-primary"><i class="fa fa-refresh"></i></button>&nbsp;<?php echo $text_duell_integration_manual_sync; ?>
            </div>
          </div>


        </form>


        <div class="col-sm-12 col-userguide" >

          <p><?php echo $text_user_guide; ?></p>





          <p>&nbsp;</p>
          <p>  <b><?php echo $text_cron_steup_title_curl; ?></b></p>
          <ul  style="list-style-type:circle">
            <li>
              <?php echo $text_every_hours; ?>:&nbsp; <b>* */3 * * * /usr/bin/curl <?php echo $cron_link; ?> >/dev/null 2>&1</b>
            </li>
            <li>
              <?php echo $text_every_night; ?>:&nbsp; <b>* 3 * * * /usr/bin/curl <?php echo $cron_link; ?> >/dev/null 2>&1</b>
            </li>
          </ul>
          <p>&nbsp;</p>


        </div>

      </div>
    </div>
  </div>
</div>
<style>
  .col-userguide{
    background: #f6f6f6;
    padding: 10px;
    margin-bottom: 17px;
    border: 1px solid #e4e4e4;
    border-radius: 3px;
  }
</style>
  <script type="text/javascript"><!--
  $('#button-syncmanually').on('click', function () {
      $.ajax({
        url: 'index.php?route=extension/module/duell_integration/manualsync&token=<?php echo $token; ?>',
        type: 'post',
        dataType: 'json',
        cache: false,
        beforeSend: function () {
          $('#button-syncmanually').button('loading');
        },
        complete: function () {
          $('#button-syncmanually').button('reset');
        },
        success: function (json) {
          console.log(json);
          if (json['success']) {
            alert(json['message']);
          } else {
            alert(json['message']);
          }
        },
        error: function (xhr, ajaxOptions, thrownError) {
          alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
      });

    });
    //--></script>
<?php echo $footer; ?>