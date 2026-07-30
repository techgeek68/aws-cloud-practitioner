import React, { useState, useEffect } from "react";
import axios from "axios";

const API = (process.env.REACT_APP_API_URL || "http://localhost:4000") + "/api/students";

function App() {
  const [students, setStudents] = useState([]);
  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    age: "",
    college_name: "",
    program_name: "",
    year: "",
    semester: ""
  });
  const [editId, setEditId] = useState(null);
  const [searchForm, setSearchForm] = useState({ first_name: "", last_name: "" });
  const [searchResult, setSearchResult] = useState(null);
  const [msg, setMsg] = useState("");
  const [msgType, setMsgType] = useState("success");

  useEffect(() => {
    fetchStudents();
  }, []);

  const fetchStudents = async () => {
    try {
      const res = await axios.get(API);
      setStudents(res.data);
    } catch (err) {
      showMsg("Failed to load students. Is the backend running?", "error");
    }
  };

  const showMsg = (text, type = "success") => {
    setMsg(text);
    setMsgType(type);
    setTimeout(() => setMsg(""), 4000);
  };

  const emptyForm = {
    first_name: "", last_name: "", age: "",
    college_name: "", program_name: "", year: "", semester: ""
  };

  const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });
  const handleSearchChange = (e) => setSearchForm({ ...searchForm, [e.target.name]: e.target.value });

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      if (editId) {
        await axios.put(`${API}/${editId}`, form);
        showMsg("Student updated successfully!");
        setEditId(null);
      } else {
        await axios.post(API, form);
        showMsg("Student added successfully!");
      }
      setForm(emptyForm);
      fetchStudents();
    } catch (err) {
      showMsg(err.response?.data?.error || "An error occurred.", "error");
    }
  };

  const handleEdit = (student) => {
    setEditId(student.id);
    setForm({
      first_name:   student.first_name,
      last_name:    student.last_name,
      age:          student.age,
      college_name: student.college_name,
      program_name: student.program_name,
      year:         student.year,
      semester:     student.semester
    });
    setMsg("");
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const handleCancelEdit = () => {
    setEditId(null);
    setForm(emptyForm);
  };

  const handleDelete = async (id) => {
    if (window.confirm("Delete this student?")) {
      try {
        await axios.delete(`${API}/${id}`);
        showMsg("Student deleted successfully!");
        fetchStudents();
      } catch (err) {
        showMsg("Failed to delete student.", "error");
      }
    }
  };

  const handleSearch = async (e) => {
    e.preventDefault();
    try {
      const res = await axios.get(`${API}/search`, { params: searchForm });
      setSearchResult(res.data);
      setMsg("");
    } catch (err) {
      setSearchResult(null);
      showMsg(err.response?.data?.error || "No student found.", "error");
    }
  };

  const clearSearch = () => {
    setSearchResult(null);
    setSearchForm({ first_name: "", last_name: "" });
  };

  const styles = {
    page:       { fontFamily: "Arial, sans-serif", margin: "30px 40px", maxWidth: 1000 },
    h1:         { borderBottom: "2px solid #2c3e50", paddingBottom: 8, color: "#2c3e50" },
    msg:        { padding: "10px 14px", borderRadius: 4, marginBottom: 16,
                  background: msgType === "error" ? "#fdecea" : "#eafaf1",
                  color:      msgType === "error" ? "#c0392b" : "#1e8449",
                  border:     `1px solid ${msgType === "error" ? "#e74c3c" : "#27ae60"}` },
    form:       { marginBottom: 24, padding: 20, border: "1px solid #ddd",
                  borderRadius: 6, width: 380, background: "#fafafa" },
    label:      { display: "block", marginBottom: 12 },
    input:      { display: "block", width: "100%", marginTop: 4, padding: "6px 8px",
                  boxSizing: "border-box", border: "1px solid #bbb", borderRadius: 4 },
    btnPrimary: { padding: "8px 18px", background: "#2980b9", color: "#fff",
                  border: "none", borderRadius: 4, cursor: "pointer", marginRight: 8 },
    btnCancel:  { padding: "8px 18px", background: "#7f8c8d", color: "#fff",
                  border: "none", borderRadius: 4, cursor: "pointer" },
    btnEdit:    { padding: "4px 10px", background: "#f39c12", color: "#fff",
                  border: "none", borderRadius: 4, cursor: "pointer", marginRight: 4 },
    btnDelete:  { padding: "4px 10px", background: "#e74c3c", color: "#fff",
                  border: "none", borderRadius: 4, cursor: "pointer" },
    table:      { borderCollapse: "collapse", width: "100%", marginTop: 8 },
    th:         { background: "#2c3e50", color: "#fff", padding: "8px 10px",
                  textAlign: "left", border: "1px solid #bdc3c7" },
    td:         { padding: "7px 10px", border: "1px solid #ddd" },
    trEven:     { background: "#f2f2f2" }
  };

  return (
    <div style={styles.page}>
      <h1 style={styles.h1}>Student Database Web App</h1>

      {msg && <div style={styles.msg}>{msg}</div>}

      <h2>{editId ? "Edit Student" : "Add New Student"}</h2>
      <form onSubmit={handleSubmit} style={styles.form}>
        {[
          ["First Name",   "first_name",   "text"],
          ["Last Name",    "last_name",    "text"],
          ["Age",          "age",          "number"],
          ["College Name", "college_name", "text"],
          ["Program Name", "program_name", "text"],
          ["Year",         "year",         "number"],
          ["Semester",     "semester",     "text"]
        ].map(([label, name, type]) => (
          <label key={name} style={styles.label}>
            {label}:
            <input
              type={type} name={name} required
              value={form[name]} onChange={handleChange}
              style={styles.input}
            />
          </label>
        ))}
        <button type="submit" style={styles.btnPrimary}>
          {editId ? "Update Student" : "Add Student"}
        </button>
        {editId && (
          <button type="button" onClick={handleCancelEdit} style={styles.btnCancel}>
            Cancel
          </button>
        )}
      </form>

      <h2>Search Student</h2>
      <form onSubmit={handleSearch} style={styles.form}>
        <label style={styles.label}>
          First Name:
          <input type="text" name="first_name" required
                 value={searchForm.first_name} onChange={handleSearchChange}
                 style={styles.input} />
        </label>
        <label style={styles.label}>
          Last Name:
          <input type="text" name="last_name" required
                 value={searchForm.last_name} onChange={handleSearchChange}
                 style={styles.input} />
        </label>
        <button type="submit" style={styles.btnPrimary}>Search</button>
        {searchResult && (
          <button type="button" onClick={clearSearch} style={styles.btnCancel}>Clear</button>
        )}
      </form>

      {searchResult && (
        <div style={{ padding: 16, background: "#eaf4fb", border: "1px solid #aed6f1",
                      borderRadius: 6, marginBottom: 24, maxWidth: 400 }}>
          <h3 style={{ marginTop: 0 }}>Student Found:</h3>
          <ul style={{ lineHeight: 1.9, paddingLeft: 20 }}>
            <li><strong>Name:</strong> {searchResult.first_name} {searchResult.last_name}</li>
            <li><strong>Age:</strong> {searchResult.age}</li>
            <li><strong>College:</strong> {searchResult.college_name}</li>
            <li><strong>Program:</strong> {searchResult.program_name}</li>
            <li><strong>Year:</strong> {searchResult.year}</li>
            <li><strong>Semester:</strong> {searchResult.semester}</li>
          </ul>
        </div>
      )}

      <h2>All Students ({students.length})</h2>
      {students.length === 0 ? (
        <p style={{ color: "#7f8c8d" }}>No students yet. Add one above!</p>
      ) : (
        <table style={styles.table}>
          <thead>
            <tr>
              {["ID","First Name","Last Name","Age","College","Program","Year","Semester","Actions"]
                .map(h => <th key={h} style={styles.th}>{h}</th>)}
            </tr>
          </thead>
          <tbody>
            {students.map((s, i) => (
              <tr key={s.id} style={i % 2 === 1 ? styles.trEven : {}}>
                <td style={styles.td}>{s.id}</td>
                <td style={styles.td}>{s.first_name}</td>
                <td style={styles.td}>{s.last_name}</td>
                <td style={styles.td}>{s.age}</td>
                <td style={styles.td}>{s.college_name}</td>
                <td style={styles.td}>{s.program_name}</td>
                <td style={styles.td}>{s.year}</td>
                <td style={styles.td}>{s.semester}</td>
                <td style={styles.td}>
                  <button style={styles.btnEdit}   onClick={() => handleEdit(s)}>Edit</button>
                  <button style={styles.btnDelete} onClick={() => handleDelete(s.id)}>Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

export default App;
