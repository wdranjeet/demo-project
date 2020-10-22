(function ($, Drupal) {
  $(document).ready(function () {
   //Calling countUp()
    countUp();
    //addSpanTag for small Text
    addSpanTag();

    function countUp() {
     $(".timer").each(function () {
        var $this = $(this);
        countTo = $this.attr("data-to");
        $($this).numScroll({
          number: countTo,
          delay: 1000,
          time: 10000,
          step: 1,
        });
      });
    }

    /*
    *AddSpanTag to Tab on home Page
    */
    function addSpanTag() {
      var SerVicesSmallTags = [
        "ICEGATE",
        "SINGLE WINDOW",
        "EMPOWERING CITIZENS",
        "EASE OF DOING BUSINESS",
      ];
      $.each(SerVicesSmallTags, function (index, value) {
        $(
          "#quicktabs-icegate-services-tabs .quicktabs-tabs li a:contains(" +
            value +
            ")"
        ).html(function (_, html) {
          var regex = new RegExp(value, "g");
          return html.replace(
            regex,
            '<span class="btn-title">' + value + "</span><br>"
          );
        });
      });
    }
  });
})(jQuery, Drupal);