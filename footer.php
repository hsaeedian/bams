<script src="<?php echo h(URL); ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo h(URL); ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo h(URL); ?>js/index.js?v=<?php echo VERSION; ?>" type="module"></script>

<script>
    $(window).on("load", function() {
        setTimeout(function() {
            $("#main-loader").fadeOut(300);
        }, 1000);
    });
</script>

</body>

</html>