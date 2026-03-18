const { Builder, By, until } = require('selenium-webdriver');
const fs = require('fs');

async function dumpDashboard() {
    let driver = await new Builder().forBrowser('chrome').build();
    try {
        await driver.get('http://localhost/evote/login.php');
        await driver.findElement(By.name('email')).sendKeys('nomination_test@mca.ajce.in');
        await driver.findElement(By.name('password')).sendKeys('Password@123');
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains('student_dashboard.php'), 5000);

        await driver.sleep(2000); // Give it a moment to load
        let source = await driver.getPageSource();
        fs.writeFileSync('dashboard_dump.html', source);
        console.log("Dumped dashboard HTML.");
    } catch (e) {
        console.log("Error:", e.message);
    } finally {
        await driver.quit();
    }
}
dumpDashboard();
