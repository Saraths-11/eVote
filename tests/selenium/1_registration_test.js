const { Builder, By, until } = require('selenium-webdriver');

async function runPrimaryTest() {
    let driver = await new Builder().forBrowser('chrome').build();
    try {
        console.log("-----------------------------------------");
        console.log("STARTING TASK 1: LOGIN & DASHBOARD SURF");
        console.log("-----------------------------------------");

        // 1. Navigate and Login
        await driver.get('http://localhost/evote/login.php');
        console.log("Step: Entering Credentials...");
        await driver.findElement(By.name('email')).sendKeys('sarath123@gmail.com');
        await driver.findElement(By.name('password')).sendKeys('2004');
        await driver.findElement(By.css('button[type="submit"]')).click();

        // 2. Verify Dashboard redirection
        await driver.wait(until.urlContains('admin_dashboard.php'), 5000);
        console.log("SUCCESS: Logged in to Admin Dashboard.");

        // 3. Surf through Dashboard sections
        const sections = [
            { name: 'Manage Elections', url: 'manage_elections.php' },
            { name: 'View Participants', url: 'view_participants.php' },
            { name: 'Election Results', url: 'view_results.php' },
            { name: 'Audit Logs', url: 'view_logs.php' }
        ];

        for (let section of sections) {
            console.log("Navigating to Section: " + section.name + "...");
            await driver.get('http://localhost/evote/' + section.url);
            await driver.wait(until.urlContains(section.url), 5000);
            console.log("-> " + section.name + " page loaded successfully.");
            await driver.sleep(1000); // Visual pause for observation
        }

        // 4. Logout
        console.log("Step: Initiating Logout...");
        await driver.get('http://localhost/evote/logout.php');
        await driver.wait(until.urlContains('login.php'), 5000);

        console.log("-----------------------------------------");
        console.log("TEST PASSED: COMPLETED PROPER SEQUENCE");
        console.log("Sequence: Login -> Dashboard Surf -> Logout");
        console.log("-----------------------------------------");

    } catch (error) {
        console.error("TEST FAILED:", error.message);
    } finally {
        await driver.quit();
    }
}

runPrimaryTest();
