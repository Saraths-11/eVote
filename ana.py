# Student Performance & Course Analysis System
import pandas as pd
import matplotlib.pyplot as plt
from sklearn.linear_model import LinearRegression

# Step 1: Dataset (example marks)
data = {
    "Student_ID": [101, 102, 103, 104, 105],
    "Name": ["Anu", "Rahul", "Meera", "Joseph", "Sneha"],
    "Maths": [78, 56, 90, 45, 67],
    "Science": [85, 72, 88, 50, 70],
    "English": [69, 60, 92, 55, 73],
    "Final_Average": [77, 63, 90, 50, 70]  # actual averages
}
df = pd.DataFrame(data)

# Step 2: Course Difficulty Analysis (based on class averages)
subject_avgs = df[["Maths","Science","English"]].mean()
print("\n=== Course Difficulty Analysis ===")
for subject, avg in subject_avgs.items():
    if avg < 60:
        difficulty = "High Difficulty"
    elif avg < 80:
        difficulty = "Moderate Difficulty"
    else:
        difficulty = "Low Difficulty"
    print(f"{subject}: Avg={avg:.2f} → {difficulty}")

# Chart: Class average per subject
plt.figure(figsize=(6,4))
subject_avgs.plot(kind="bar", color=["skyblue","lightgreen","salmon"])
plt.title("Class Average per Subject")
plt.ylabel("Average Marks")
plt.show()

# Step 3: Individual Student Performance
df["Average"] = df[["Maths","Science","English"]].mean(axis=1)

def classify(avg):
    if avg >= 80:
        return "Excellent"
    elif avg >= 60:
        return "Good"
    else:
        return "Needs Improvement"

df["Category"] = df["Average"].apply(classify)
print("\n=== Individual Student Performance ===")
print(df[["Student_ID","Name","Average","Category"]])

# Chart: Student average marks
plt.figure(figsize=(8,5))
plt.bar(df["Name"], df["Average"], color="orange")
plt.title("Individual Student Average Marks")
plt.xlabel("Students")
plt.ylabel("Average Marks")
plt.show()

# Chart: Performance categories
plt.figure(figsize=(6,6))
df["Category"].value_counts().plot.pie(autopct="%1.1f%%", colors=["lightgreen","gold","salmon"])
plt.title("Student Performance Categories")
plt.ylabel("")
plt.show()

# Step 4: Class Performance (overall averages)
print("\n=== Class Performance ===")
print("Overall Class Average:", df["Average"].mean())

# Step 5: Future Prediction using Linear Regression
X = df[["Maths","Science","English"]]
y = df["Final_Average"]
model = LinearRegression()
model.fit(X, y)

# User input for new student marks
print("\nEnter new student marks for prediction:")
maths = int(input("Maths: "))
science = int(input("Science: "))
english = int(input("English: "))

new_student = [[maths, science, english]]
predicted_avg = model.predict(new_student)[0]

print("\n=== Future Prediction ===")
print("Predicted Average Marks:", round(predicted_avg,2))
print("Predicted Category:", classify(predicted_avg))