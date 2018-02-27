

<div style="border:1px solid #990000;padding-left:20px;margin:0 0 10px 0;">

    <h4>程序出了点小问题，您可以返回首页，或稍后再试。</h4>

    <p>Type: <?php echo get_class($exception); ?></p>
    <p>Message: <?php echo $message; ?></p>
    <p>Filename: <?php echo $exception->getFile(); ?></p>
    <p>Line Number: <?php echo $exception->getLine(); ?></p>

</div>