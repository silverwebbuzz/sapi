<?php include 'header.php';
include 'nav.php'; 
?>
<style>
        
        p {
        line-height: 1.4;
        margin-bottom: 10px;
        margin-top: 10px;
        font-size: 15px;
        }
        h2,  h3, h4 {
        margin-top: 20px;
        margin-bottom: 10px;
        }
        h4{
            font-size: 15px;
        }
        ul {
            padding-left: 20px;
            font-size: 15px;
        }
        li {
            margin-bottom: 10px;
            font-size: 15px;
        }
        .video-container embed {
            width: 100%;
            max-width: 800px;
            height: 400px;
            border: none;
        }
        .support-contact {
            margin-top: 40px;
            font-size: 15px;
            text-align: center;
        }
        .support-contact a {
            color: #007acc;
            text-decoration: none;
        }
    </style>
<main class="main-content">
    
    <div class="package-card">
        <h1><?= e('help.title') ?></h1>
        <p><?= e('help.subtitle') ?></p>

        <p><?= e('help.intro') ?></p>

        <h2><?= e('help.options_title') ?></h2>
        <ul>
            <li><?= e('help.option_video') ?></li>
            <li><?= e('help.option_instructions') ?></li>
            <li><?= e('help.option_contact') ?></li>
        </ul>

        <p><?= e('help.commitment') ?></p>

        <h2><?= e('help.video_title') ?></h2>
        <div class="video-container">
        <embed src="https://www.youtube.com/embed/eF9gLm6UqA4" type="video/mp4" style="width:800px; height:400px;" />
        </div>

        <h2><?= e('help.instructions_title') ?></h2>

        <h3><?= e('help.free_plan_title') ?></h3>
        <ul>
            <li><?= e('help.free_1') ?></li>
            <li><?= e('help.free_2') ?></li>
            <?php // These carry <strong> around menu paths, so they render as markup. ?>
            <li><?= t_html('help.free_3') ?></li>
            <li><?= e('help.free_4') ?></li>
        </ul>

        <h3><?= e('help.premium_title') ?></h3>
        <p><strong><?= e('help.premium_intro') ?></strong></p>

        <h4><?= e('help.auto_delivery_title') ?></h4>
        <ul>
            <li><?= t_html('help.auto_1') ?></li>
            <li><?= e('help.auto_2') ?></li>
            <li><?= e('help.auto_3') ?></li>
        </ul>

        <h4><?= e('help.email_config_title') ?></h4>
        <ul>
            <li><?= t_html('help.email_1') ?></li>
            <li><?= e('help.email_2') ?></li>
            <li><?= e('help.email_3') ?></li>
        </ul>



        <div class="support-contact">
            <?= e('help.contact') ?> <a href="mailto:support.sapi@silverwebbuzz.com">support.sapi@silverwebbuzz.com</a>
        </div>
    </div>
</main>
<!-- Message Box-->
<div id="message-box" style="display:none; position:fixed; top:20px; right:20px; padding:10px 20px; border-radius:5px; color:#fff; z-index:9999; font-weight:bold;"></div>

<!-- Modal Wrapper -->
<div id="invoiceModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999;">
  <div style="position:relative; width:80%; max-width:800px; margin:5% auto; background:#fff; padding:20px;">
    <span style="position:absolute; top:10px; right:20px; cursor:pointer;" onclick="closeInvoiceModal()">✖</span>
    <embed id="invoiceFrame" type="application/pdf" style="width:100%; height:600px; border:none;" />
  </div>
</div>

<?php include 'footer.php'; ?>