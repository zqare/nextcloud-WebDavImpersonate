<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
?>

<div class="section">
    <h2>TEST PAGE</h2>
    <p>If you see this, the template is loading.</p>
    <button id="test-btn">Test Button</button>
    <button id="test-btn2">Test Button 2</button>
</div>

<?php
use OCP\Util;
$nonce = Util::callRegister();
?>
<script nonce="<?php p($nonce); ?>">
console.log('TEST SCRIPT LOADED!');

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded fired');
    
    var btn1 = document.getElementById('test-btn');
    var btn2 = document.getElementById('test-btn2');
    
    console.log('btn1 found:', btn1);
    console.log('btn2 found:', btn2);
    
    if (btn1) {
        btn1.addEventListener('click', function() {
            console.log('Button 1 clicked!');
            alert('Button 1 works!');
        });
        console.log('Event listener attached to btn1');
    }
    
    if (btn2) {
        btn2.addEventListener('click', function() {
            console.log('Button 2 clicked!');
            alert('Button 2 works!');
        });
        console.log('Event listener attached to btn2');
    }
});
</script>
