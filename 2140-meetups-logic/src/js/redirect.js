var count = 7;
var redirect = data.url;

function countDown() {
    var timer = document.getElementById("timer");
    if (count > 0) {
        count--;
        timer.innerHTML = "<span class='timer'>" + count + "</span></p>";
        setTimeout("countDown()", 1000);
    } else {
        window.location.href = redirect;
    }
}

countDown();