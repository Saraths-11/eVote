// Quick diagnostic: run each test and print pass/fail without launching Chrome for long
const { Builder, By, until } = require('selenium-webdriver');

async function quickLogin(email, password, expectUrl) {
    let driver = await new Builder().forBrowser('chrome').build();
    try {
        await driver.get('http://localhost/evote/login.php');
        await driver.findElement(By.name('email')).sendKeys(email);
        await driver.findElement(By.name('password')).sendKeys(password);
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains(expectUrl), 6000);
        return { ok: true, url: await driver.getCurrentUrl() };
    } catch (e) {
        return { ok: false, error: e.message };
    } finally {
        await driver.quit();
    }
}

(async () => {
    console.log('\n=== ADMIN LOGIN DIAGNOSIS ===');
    let r1 = await quickLogin('sarath123@gmail.com', '2004', 'admin_dashboard.php');
    console.log(r1.ok ? 'PASS: Admin login works' : 'FAIL: ' + r1.error);

    console.log('\n=== STUDENT LOGIN DIAGNOSIS ===');
    let r2 = await quickLogin('nomination_test@mca.ajce.in', 'Password@123', 'student_dashboard.php');
    console.log(r2.ok ? 'PASS: Candidate student login works' : 'FAIL: ' + r2.error);

    // Check if vote button / register button exists
    if (r2.ok) {
        let driver2 = await new Builder().forBrowser('chrome').build();
        try {
            await driver2.get('http://localhost/evote/login.php');
            await driver2.findElement(By.name('email')).sendKeys('nomination_test@mca.ajce.in');
            await driver2.findElement(By.name('password')).sendKeys('Password@123');
            await driver2.findElement(By.css('button[type="submit"]')).click();
            await driver2.wait(until.urlContains('student_dashboard.php'), 6000);

            await driver2.get('http://localhost/evote/student_dashboard.php');
            await driver2.sleep(2000);

            let pageSource = await driver2.getPageSource();
            // Check what election-related buttons are visible
            let hasRegister = pageSource.includes('Register Now');
            let hasVote = pageSource.includes('Vote Now') || pageSource.includes('Vote');
            let hasElection = pageSource.includes('Live Selenium Election') || pageSource.includes('election');
            console.log('\n=== Dashboard Content Check ===');
            console.log('Has "Register Now" button:', hasRegister);
            console.log('Has "Vote"/"Vote Now" button:', hasVote);
            console.log('Has election card visible:', hasElection);

            // Extract all anchor/button text
            let anchors = await driver2.findElements(By.css('a, button'));
            console.log('\nAll clickable elements on dashboard:');
            for (let el of anchors) {
                try {
                    let txt = (await el.getText()).trim();
                    if (txt) console.log(' -', txt);
                } catch (e) { }
            }
        } catch (e) {
            console.log('Dashboard inspection error:', e.message);
        } finally {
            await driver2.quit();
        }
    }
})();
