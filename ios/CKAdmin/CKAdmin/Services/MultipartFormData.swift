import Foundation

struct MultipartFormData {
    private let boundary: String
    private var fields: [(name: String, value: String)] = []
    private var files: [(name: String, fileName: String, mimeType: String, data: Data)] = []
    
    init(boundary: String = UUID().uuidString) {
        self.boundary = boundary
    }
    
    var contentType: String {
        "multipart/form-data; boundary=\(boundary)"
    }
    
    mutating func addField(name: String, value: String) {
        fields.append((name: name, value: value))
    }
    
    mutating func addFile(name: String, fileName: String, mimeType: String, data: Data) {
        files.append((name: name, fileName: fileName, mimeType: mimeType, data: data))
    }
    
    func buildBody() -> Data {
        var body = Data()
        let lineBreak = "\r\n"
        
        for field in fields {
            body.append("--\(boundary)\(lineBreak)")
            body.append("Content-Disposition: form-data; name=\"\(field.name)\"\(lineBreak)\(lineBreak)")
            body.append("\(field.value)\(lineBreak)")
        }
        
        for file in files {
            body.append("--\(boundary)\(lineBreak)")
            body.append("Content-Disposition: form-data; name=\"\(file.name)\"; filename=\"\(file.fileName)\"\(lineBreak)")
            body.append("Content-Type: \(file.mimeType)\(lineBreak)\(lineBreak)")
            body.append(file.data)
            body.append(lineBreak)
        }
        
        body.append("--\(boundary)--\(lineBreak)")
        return body
    }
}

extension Data {
    mutating func append(_ string: String) {
        if let data = string.data(using: .utf8) {
            append(data)
        }
    }
}
