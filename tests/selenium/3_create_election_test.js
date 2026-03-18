const { Builder, By, until } = require('selenium-webdriver');

async function runCreateElectionTest() {
    let driver = await new Builder().forBrowser('chrome').build();
    try {
        console.log("-----------------------------------------");
        console.log("STARTING TEST 3: CREATE ELECTION (LIVE)");
        console.log("-----------------------------------------");

        // 1. LOGIN
        await driver.get('http://localhost/evote/login.php');
        console.log("Step 1: Logging in...");
        await driver.findElement(By.name('email')).sendKeys('sarath123@gmail.com');
        await driver.findElement(By.name('password')).sendKeys('2004');
        await driver.findElement(By.css('button[type="submit"]')).click();
        await driver.wait(until.urlContains('admin_dashboard.php'), 5000);

        // 2. NAVIGATE
        console.log("Step 2: Navigating to Create Election...");
        await driver.get('http://localhost/evote/create_election.php');

        // 3. FILL FORM (Robust method using JavaScript for dates)
        console.log("Step 3: Filling Election Details...");
        await driver.findElement(By.name('title')).sendKeys('Live Selenium Election ' + Date.now());
        await driver.findElement(By.name('description')).sendKeys('Automated test created in real-time.');

        // Robust date setting via JS
        const setDate = async (id, value) => {
            await driver.executeScript(`document.getElementById("${id}").value = "${value}";`);
        };

        // Dates for 2026
        await setDate('registration_start', '2026-05-01T10:00');
        await setDate('registration_end', '2026-05-05T10:00');
        await setDate('cancellation_start', '2026-05-06T10:00');
        await setDate('cancellation_end', '2026-05-10T10:00');
        await setDate('election_start', '2026-05-15T09:00');
        await setDate('election_end', '2026-05-15T16:00');

        console.log("Step 4: Submitting form...");
        await driver.sleep(1000); // Visual pause
        await driver.findElement(By.css('button[type="submit"]')).click();

        // 4. VERIFY
        await driver.wait(until.urlContains('manage_elections.php'), 5000);
        console.log("-----------------------------------------");
        console.log("TEST PASSED: ELECTION CREATED SUCCESSFULLY");
        console.log("Status: Reached Management Dashboard.");
        console.log("-----------------------------------------");

        await driver.sleep(2000); // Pause for user to see result
    } catch (error) {
        console.error("TEST FAILED:", error.message);
    } finally {
        await driver.quit();
    }
}

runCreateElectionTest();
